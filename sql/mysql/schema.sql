/* Records the date and time a node was moved to the trash */
drop table if exists pt_trash;
create table pt_trash(
  node_id int not null primary key,
  trashed int not null);
