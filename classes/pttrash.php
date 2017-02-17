<?php

/**
 * Persistent object to map to pt_trash.
 */
class ptTrash extends eZPersistentObject
{
    /**
     * Schema definition
     * eZPersistentObject implementation for ezsite_data table
     * @see kernel/classes/ezpersistentobject.php
     * @return array
     */
    public static function definition()
    {
        return array('fields' =>
            array('node_id' => array('name' => 'node_id',
            'datatype' => 'integer',
            'default' => null,
            'required' => true),

            'trashed' => array('name' => 'trashed',
                'datatype' => 'integer',
                'default' => null,
                'required' => true),

        ),

            'keys' => array('node_id'),
            'class_name' => 'ptTrash',
            'name' => 'pt_trash',
            'function_attributes' => array()
        );
    }


    static function removeExisting( $node_id ) {
        $existing_trash = ptTrash::fetch($node_id);
        if ($existing_trash) {
            $existing_trash->remove();
        }
    }

    static function fetch( $node_id, $asObject = true )
    {
        if ( !$node_id )
            return null;
        return eZPersistentObject::fetchObject( ptTrash::definition(),
            null,
            array( 'node_id' => $node_id ),
            $asObject );
    }

}