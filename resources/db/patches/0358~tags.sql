# Add tables related to tagging releases

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS tagging_tag_groups;
create table tagging_tag_groups
(
	id int unsigned auto_increment primary key,
	slug varchar(125) not null,
	name varchar(125) not null
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_unicode_ci
ROW_FORMAT=DYNAMIC;

create index tagging_tag_groups_slug_index on tagging_tag_groups (slug);

DROP TABLE IF EXISTS tagging_tagged;
create table tagging_tagged
(
	id int unsigned auto_increment primary key,
	taggable_id int unsigned not null,
	taggable_type varchar(125) not null,
	tag_name varchar(125) not null,
	tag_slug varchar(125) not null
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_unicode_ci
ROW_FORMAT=DYNAMIC;

create index tagging_tagged_tag_slug_index on tagging_tagged (tag_slug);

create index tagging_tagged_taggable_id_index on tagging_tagged (taggable_id);

create index tagging_tagged_taggable_type_index on tagging_tagged (taggable_type);

DROP TABLE IF EXISTS tagging_tags;
create table tagging_tags
(
	id int unsigned auto_increment
		primary key,
	tag_group_id int unsigned null,
	slug varchar(125) not null,
	name varchar(125) not null,
	suggest tinyint(1) default 0 not null,
	count int unsigned default 0 not null,
	constraint tagging_tags_tag_group_id_foreign foreign key (tag_group_id) references tagging_tag_groups (id)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_unicode_ci
ROW_FORMAT=DYNAMIC;

create index tagging_tags_slug_index on tagging_tags (slug);

SET FOREIGN_KEY_CHECKS = 1;
