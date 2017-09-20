<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2014 VINADES.,JSC. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate 31/05/2010, 00:36
 */

define( 'NV_SYSTEM', true );

// Xac dinh thu muc goc cua site
define( 'NV_ROOTDIR', pathinfo( str_replace( DIRECTORY_SEPARATOR, '/', __file__ ), PATHINFO_DIRNAME ) );

require NV_ROOTDIR . '/includes/mainfile.php';

$prefix2 = "prefix_nkv2";
$user_prefix2 = "prefix_nkv2";

$db->query( "DELETE FROM `nv4_users` WHERE `userid`>2" );
$db->query( "DELETE FROM `nv4_users_info` WHERE `userid`>2" );
$db->query( "DELETE FROM `nv4_authors` WHERE `admin_id`>2" );

try
{
	$result = $db->query( "SELECT * FROM `" . $user_prefix2 . "_authors`" );
	while( $_user = $result->fetch() )
	{
		$_user['in_groups'] = '';
		$_user['birthday'] = 0;
		$_user['view_mail'] = 0;
		$global_config['idsite'] = 0;

		$md5 = $_user['pwd'];
		$ret = '';
		for( $i = 0; $i < 32; $i += 2 )
		{
			$ret .= chr( hexdec( $md5{$i + 1} ) + hexdec( $md5{$i} ) * 16 );
		}

		$password = '{MD5}' . base64_encode( $ret );

		$md5username = nv_md5safe( $_user['aid'] );

		$sql = "INSERT INTO " . NV_USERS_GLOBALTABLE . " (
				username, md5username, password, email, first_name, last_name, gender, birthday, sig, regdate,
				question, answer, passlostkey, view_mail,
				remember, in_groups, active, checknum, last_login, last_ip, last_agent, last_openid, idsite)
				VALUES (
				:username,
				:md5_username,
				:password,
				:email,
				:first_name,
				:last_name,
				:gender,
				" . $_user['birthday'] . ",
				:sig,
				" . NV_CURRENTTIME . ",
				:question,
				:answer,
				'',
				 " . $_user['view_mail'] . ",
				 1,
				 '" . implode( ',', $_user['in_groups'] ) . "', 1, '', 0, '', '', '', " . $global_config['idsite'] . ")";
		$data_insert = array();
		$data_insert['username'] = $_user['aid'];
		$data_insert['md5_username'] = $md5username;
		$data_insert['password'] = $password;
		$data_insert['email'] = $_user['email'];
		$data_insert['first_name'] = $_user['name'];
		$data_insert['last_name'] = '';
		$data_insert['gender'] = '';
		$data_insert['sig'] = '';
		$data_insert['question'] = '';
		$data_insert['answer'] = '';

		try
		{
			$stmt = $db->prepare( $sql );
			foreach( $data_insert as $key => $value )
			{
				$stmt->bindParam( ':' . $key, $data_insert[$key], PDO::PARAM_STR, strlen( $value ) );
			}

			$stmt->execute();
			$userid = $db->lastInsertId();

		}
		catch( PDOException $e )
		{
			echo ( $e->getMessage() . '<br>' );
		}

		if( $userid )
		{
			$db->query( 'INSERT INTO ' . NV_USERS_GLOBALTABLE . '_info (userid) VALUES (' . $userid . ')' );
			$db->query( "INSERT INTO `nv4_authors` (`admin_id`, `editor`, `lev`, `files_level`, `position`, `addtime`, `edittime`, `is_suspend`, `susp_reason`, `check_num`, `last_login`, `last_ip`, `last_agent`) VALUES
			(" . $userid . ", 'ckeditor', 2, 'adobe,archives,audio,documents,flash,images,real,video|1|1|1', " . $db->quote( $_user['name'] ) . ", 0, 0, 0, '', '', 0, '', '')" );
		}

	}
}
catch( PDOException $e )
{
	print_r( $e );
}
die( 'Xong' );