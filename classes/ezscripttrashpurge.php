<?php
/**
 * This class handles purging of trash items older than x days old.
 * It is used by both the script and cronjob.
 */
class eZScriptTrashPurge
{
    const SECONDS_PER_DAY = 86400;

    /**
     * eZCLI object used along running the operation.
     *
     * @var eZCLI
     */

    protected $cli;

    /**
     * Whether the operation should be quiet.
     *
     * @var bool
     */
    protected $quiet;

    /**
     * Whether memory monitoring is active.
     *
     * @var bool
     */
    protected $memoryMonitoring;

    /**
     * eZScript object used along running the operation.
     *
     * @var eZScript
     */
    protected $script;

    /**
     * Filename to use while logging memory monitoring.
     *
     * @var string
     */
    protected $logFile;

    /**
     * Constructor of eZScriptTrashPurge.
     *
     * @param eZCLI $cli The instance of eZCLI.
     * @param bool $quiet Whether the operation should be quiet or not.
     * @param bool $memoryMonitoring Set to true to turn on memory monitoring.
     * @param eZScript $script Optional eZScript object used while running.
     * @param string $logFile Log file to use for memory monitoring.
     */
    public function __construct( eZCLI $cli, $quiet = true, $memoryMonitoring = false, eZScript $script = null, $logFile = "trashpurge.log" )
    {
        $this->cli = $cli;
        $this->quiet = $quiet;
        $this->memoryMonitoring = $memoryMonitoring;
        $this->script = $script;
        $this->logFile = $logFile;
    }

    /**
     * Executes the purge operation
     *
     * @param int|null $iterationLimit Number of trashed objects to treat per iteration, use null to use a default value.
     * @param int|null $sleep Number of seconds to sleep between two iterations, use null to use a default value.
     *
     * @return bool True if the operation succeeded.
     */
    public function run( $iterationLimit = 10, $sleep = 1 )
    {
        if ( $iterationLimit === null )
        {
            $iterationLimit = 10;
        }

        if ( $sleep === null )
        {
            $sleep = 1;
        }

        if ( $this->memoryMonitoring )
        {
            eZLog::rotateLog( $this->logFile );
            $this->cli->output( "Logging memory usage to {$this->logFile}" );
        }

        $trashIni = eZINI::instance('trash.ini');
        $daysInTrashBeforePurge = $trashIni->variable('TrashSettings', 'DaysInTrashBeforePurge');
        if ($daysInTrashBeforePurge === false) {
            $daysInTrashBeforePurge = 7;
        }
        $purgeDateTimeCutoff = time() - (self::SECONDS_PER_DAY * $daysInTrashBeforePurge);


        $this->cli->output( "Purging trash items older than $daysInTrashBeforePurge days old:" );

        $this->monitor( "start" );

        $db = eZDB::instance();

        // Get user's ID who can remove subtrees. (Admin by default with userID = 14)
        $userCreatorID = eZINI::instance()->variable( "UserSettings", "UserCreatorID" );
        $user = eZUser::fetch( $userCreatorID );
        if ( !$user )
        {
            $this->cli->error( "Cannot get user object with userID = '$userCreatorID'.\n(See site.ini[UserSettings].UserCreatorID)" );
            return false;
        }
        eZUser::setCurrentlyLoggedInUser( $user, $userCreatorID );

        $trashCount = eZContentObjectTrashNode::trashListCount( false );
        if ( !$this->quiet )
        {
            $this->cli->output( "Found total $trashCount object(s) in trash." );
        }
        if ( $trashCount == 0 )
        {
            return true;
        }

        $trashList = eZContentObjectTrashNode::trashList( );
        $iteration = $iterationLimit;

        $db->begin();

        foreach ($trashList as $trashNode) {

            // determine if object is older than cutoff
            $nodeId = $trashNode->attribute('node_id');
            if ($this->isTrashNodeOldEnoughToPurge($nodeId, $purgeDateTimeCutoff)) {

                if (!$this->quiet) {
                    $this->cli->output("Node $nodeId is old enough to purge. Removing it.");
                }

                $object = $trashNode->attribute( 'object' );
                $this->monitor( "purge" );

                $object->purge();

                ptTrash::removeExisting($nodeId);
            } else {
                if (!$this->quiet) {
                    $this->cli->output("Node $nodeId is too new to purge. Skipping it.");
                }
            }

            $iteration --;

            // we have emptied a batch of trash nodes. Commit transaction and tee up the next batch
            if ($iteration <= 0) {

                // interim commit
                if ( !$db->commit() )
                {
                    $this->cli->output();
                    $this->cli->error( 'Trash has not been emptied, impossible to commit the whole transaction' );
                    return false;
                }
                eZContentObject::clearCache();

                $db->begin();
                $iteration = $iterationLimit;
                if ( $sleep > 0 )
                {
                    sleep( $sleep );
                }
            }
        }

        // final commit
        if ( !$db->commit() )
        {
            $this->cli->output();
            $this->cli->error( 'Trash has not been emptied, impossible to commit the whole transaction' );
            return false;
        }
        eZContentObject::clearCache();

        if ( !$this->quiet )
        {
            $this->cli->output( 'Trash successfully emptied' );
        }

        $this->monitor( "end" );

        return true;
    }

    /**
     * Log memory usage.
     *
     * @param string $text Text to use while logging.
     */
    private function monitor( $text )
    {
        if ( $this->memoryMonitoring )
        {
            eZLog::write( "mem [$text]: " . memory_get_usage(), $this->logFile );
        }
    }

    /**
     * Checks the date/time when the node was added to the trash. Returns true if the
     * node should be purged. If a node has no entry in the pt_trash table, it's also considered OK to delete.
     *
     * @param $nodeId Node to check.
     * @param $purgeDateTimeCutoff The latest date that a node should remain in trash
     */
    private function isTrashNodeOldEnoughToPurge($nodeId, $purgeDateTimeCutoff) {
        $trashTimingInfo = ptTrash::fetch($nodeId);
        if (!$trashTimingInfo) {
            return true;
        }

        return ($trashTimingInfo->attribute('trashed') < $purgeDateTimeCutoff);
    }
}

?>
