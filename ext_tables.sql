#
# Table structure for table 'cache_sys_dmail_stat'
#
CREATE TABLE cache_sys_dmail_stat (
  mid int(11) DEFAULT '0' NOT NULL,
  rid int(11) DEFAULT '0' NOT NULL,
  rtbl char(1) DEFAULT '' NOT NULL,
  pings tinyint(3) unsigned DEFAULT '0' NOT NULL,
  plain_links tinyint(3) unsigned DEFAULT '0' NOT NULL,
  html_links tinyint(3) unsigned DEFAULT '0' NOT NULL,
  links tinyint(3) unsigned DEFAULT '0' NOT NULL,
  recieved_html tinyint(3) unsigned DEFAULT '0' NOT NULL,
  recieved_plain tinyint(3) unsigned DEFAULT '0' NOT NULL,
  size int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  pings_first int(11) DEFAULT '0' NOT NULL,
  pings_last int(11) DEFAULT '0' NOT NULL,
  html_links_first int(11) DEFAULT '0' NOT NULL,
  html_links_last int(11) DEFAULT '0' NOT NULL,
  plain_links_first int(11) DEFAULT '0' NOT NULL,
  plain_links_last int(11) DEFAULT '0' NOT NULL,
  links_first int(11) DEFAULT '0' NOT NULL,
  links_last int(11) DEFAULT '0' NOT NULL,
  response_first int(11) DEFAULT '0' NOT NULL,
  response_last int(11) DEFAULT '0' NOT NULL,
  response tinyint(3) unsigned DEFAULT '0' NOT NULL,
  time_firstping int(11) DEFAULT '0' NOT NULL,
  time_lastping int(11) DEFAULT '0' NOT NULL,
  time_first_link int(11) DEFAULT '0' NOT NULL,
  time_last_link int(11) DEFAULT '0' NOT NULL,
  firstlink tinyint(4) DEFAULT '0' NOT NULL,
  firstlink_time int(11) DEFAULT '0' NOT NULL,
  secondlink tinyint(4) DEFAULT '0' NOT NULL,
  secondlink_time int(11) DEFAULT '0' NOT NULL,
  thirdlink tinyint(4) DEFAULT '0' NOT NULL,
  thirdlink_time int(11) DEFAULT '0' NOT NULL,
  KEY mid (mid)
);


#
# Table structure for table 'sys_dmail'
#
CREATE TABLE sys_dmail (
  uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
  pid int(11) unsigned DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  type tinyint(4) unsigned DEFAULT '0' NOT NULL,
  page int(11) unsigned DEFAULT '0' NOT NULL,
  attachment tinyblob NOT NULL,
  subject varchar(120) DEFAULT '' NOT NULL,
  from_email varchar(80) DEFAULT '' NOT NULL,
  from_name varchar(80) DEFAULT '' NOT NULL,
  replyto_email varchar(80) DEFAULT '' NOT NULL,
  replyto_name varchar(80) DEFAULT '' NOT NULL,
  organisation varchar(80) DEFAULT '' NOT NULL,
  priority tinyint(4) unsigned DEFAULT '0' NOT NULL,
  sendOptions tinyint(4) unsigned DEFAULT '0' NOT NULL,
  HTMLParams varchar(80) DEFAULT '' NOT NULL,
  plainParams varchar(80) DEFAULT '' NOT NULL,
  issent tinyint(4) unsigned DEFAULT '0' NOT NULL,
  renderedsize int(11) unsigned DEFAULT '0' NOT NULL,
  mailContent mediumblob NOT NULL,
  scheduled int(10) unsigned DEFAULT '0' NOT NULL,
  query_info mediumblob NOT NULL,
  scheduled_begin int(10) unsigned DEFAULT '0' NOT NULL,
  scheduled_end int(10) unsigned DEFAULT '0' NOT NULL,
  return_path varchar(80) DEFAULT '' NOT NULL,
  long_link_rdct_url varchar(80) DEFAULT '' NOT NULL,
  long_link_mode tinyint(4) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid)
);

#
# Table structure for table 'sys_dmail_group'
#
CREATE TABLE sys_dmail_group (
  uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
  pid int(11) unsigned DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
  type tinyint(4) unsigned DEFAULT '0' NOT NULL,
  title tinytext NOT NULL,
  description text NOT NULL,
  query blob NOT NULL,
  static_list int(11) DEFAULT '0' NOT NULL,
  list mediumblob NOT NULL,
  csv tinyint(4) DEFAULT '0' NOT NULL,
  pages tinyblob NOT NULL,
  whichtables tinyint(4) DEFAULT '0' NOT NULL,
  recursive tinyint(4) DEFAULT '0' NOT NULL,
  mail_groups tinyblob NOT NULL,
  select_categories int(11) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY parent (pid)
);

#
# Table structure for table 'sys_dmail_group_mm'
#
CREATE TABLE sys_dmail_group_mm (
  uid_local int(11) unsigned DEFAULT '0' NOT NULL,
  uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
  tablenames varchar(30) DEFAULT '' NOT NULL,
  sorting int(11) unsigned DEFAULT '0' NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

#
# Table structure for table 'sys_dmail_maillog'
#
CREATE TABLE sys_dmail_maillog (
  uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
  mid int(11) unsigned DEFAULT '0' NOT NULL,
  rid int(11) unsigned DEFAULT '0' NOT NULL,
  rtbl char(1) DEFAULT '' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  url tinyblob NOT NULL,
  size int(11) unsigned DEFAULT '0' NOT NULL,
  parsetime int(11) unsigned DEFAULT '0' NOT NULL,
  response_type tinyint(4) DEFAULT '0' NOT NULL,
  html_sent tinyint(4) DEFAULT '0' NOT NULL,
  url_id tinyint(4) DEFAULT '0' NOT NULL,
  return_content mediumblob NOT NULL,
  return_code smallint(6) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
  KEY rid (rid,rtbl,mid,response_type,uid),
  KEY mid (mid,response_type,rtbl,rid)
);


# THESE create statements will NOT work if this file is piped into MySQL. 
# Rather they will be detected by the Typo3 Install Tool and through that 
# you should upgrade the tables to content these fields.

CREATE TABLE fe_users (
  module_sys_dmail_category int(10) unsigned DEFAULT '0' NOT NULL,
  module_sys_dmail_html tinyint(3) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tt_address (
  module_sys_dmail_category int(10) unsigned DEFAULT '0' NOT NULL,
  module_sys_dmail_html tinyint(3) unsigned DEFAULT '0' NOT NULL,
);

CREATE TABLE tt_content (
  module_sys_dmail_category int(10) unsigned DEFAULT '0' NOT NULL,
);

