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

$step = $nv_Request->get_int( 'step', 'get', 1 );
$nextstep = $step;
$module_data = 'download';
if( $step == 1 )
{
	$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_download`" );
	$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_download_categories`" );
	$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_download_report`" );
	$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_download_tmp`" );
}
elseif( $step == 2 )
{

	function nv_download_add_cat( $parentid = 0 )
	{
		global $db, $prefix2;
		$new_weight = 0;
		$array = array();

		$result = $db->query( "SELECT * FROM `" . $prefix2 . "_files_categories` where parentid='" . $parentid . "'" );
		while( $array = $result->fetch() )
		{
			$array['title'] = mb_convert_encoding( $array['title'], 'windows-1252', 'UTF-8' );
			$array['cdescription'] = mb_convert_encoding( $array['cdescription'], 'windows-1252', 'UTF-8' );

			$array['alias'] = change_alias( $array['title'] );
			$array['groups_view'] = "6";
			$array['groups_download'] = "6";
			$new_weight++;
			try
			{
				$db->query( "INSERT INTO `" . NV_PREFIXLANG . "_download_categories` (`id`, `parentid`, `title`, `alias`, `description`, `groups_view`, `groups_download`,
					`numsubcat`, `subcatid`, `viewcat`, `numlink`, `sort`, `lev`, `weight`, `status`) VALUES (
		            " . $array['cid'] . ",
		            " . $array['parentid'] . ",
		            " . $db->quote( $array['title'] ) . ",
		            " . $db->quote( $array['alias'] ) . ",
		            " . $db->quote( $array['cdescription'] ) . ",
		            " . $db->quote( $array['groups_view'] ) . ",
		            " . $db->quote( $array['groups_download'] ) . ",
					0, '', 'viewcat_list_new', 5, 0, 0, " . $new_weight . ", 1)" );
			}
			catch( PDOException $e )
			{
				print_r( $e );
				die();
			}
			nv_download_add_cat( $array['cid'] );
		}
		unset( $result, $array );
	}

	function nv_fix_cat_order( $parentid = 0, $order = 0, $lev = 0 )
	{
		global $db, $module_data;

		$sql = 'SELECT id, parentid FROM ' . NV_PREFIXLANG . '_' . $module_data . '_categories WHERE parentid=' . $parentid . ' ORDER BY weight ASC';
		$result = $db->query( $sql );
		$array_cat_order = array();
		while( $row = $result->fetch() )
		{
			$array_cat_order[] = $row['id'];
		}
		$result->closeCursor();
		$weight = 0;
		if( $parentid > 0 )
		{
			++$lev;
		}
		else
		{
			$lev = 0;
		}
		foreach( $array_cat_order as $catid_i )
		{
			++$order;
			++$weight;
			$sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . '_categories SET weight=' . $weight . ', sort=' . $order . ', lev=' . $lev . ' WHERE id=' . intval( $catid_i );
			$db->query( $sql );
			$order = nv_fix_cat_order( $catid_i, $order, $lev );
		}
		$numsubcat = $weight;
		if( $parentid > 0 )
		{
			$sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . '_categories SET numsubcat=' . $numsubcat;
			if( $numsubcat == 0 )
			{
				$sql .= ",subcatid='', viewcat='viewcat_list_new'";
			}
			else
			{
				$sql .= ",subcatid='" . implode( ',', $array_cat_order ) . "'";
			}
			$sql .= ' WHERE id=' . intval( $parentid );
			$db->query( $sql );
		}
		return $order;
	}

	try
	{
		nv_download_add_cat();
		nv_fix_cat_order();
	}
	catch( PDOException $e )
	{
		print_r( $e );
		die();
	}
}
elseif( $step == 3 )
{
	$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_download`" );

	$result = $db->query( "SELECT *, UNIX_TIMESTAMP(`date`) as addtime FROM `" . $prefix2 . "_files`" );
	while( $array = $result->fetch() )
	{
		$rowcontent['userid'] = 1;
		$alias = change_alias( $array['title'] );
		$array['introtext'] = nv_clean60( strip_tags( $array['description'] ), 200 );

		$array['user_id'] = 0;
		$array['user_name'] = "";
		if( $array['url'] == "#" )
		{
			$array['url'] = "";
		}
		$array['fileupload'] = "";
		$array['fileimage'] = "";
		$array['copyright'] = "";
		$array['groups_comment'] = "6";
		$array['rating_detail'] = $array['totalvotes'] . "|" . $array['votes'];

		$array['title'] = mb_convert_encoding( $array['title'], 'windows-1252', 'UTF-8' );
		$array['description'] = mb_convert_encoding( $array['description'], 'windows-1252', 'UTF-8' );
		$array['user_name'] = mb_convert_encoding( $array['user_name'], 'windows-1252', 'UTF-8' );
		$array['name'] = mb_convert_encoding( $array['name'], 'windows-1252', 'UTF-8' );
		$array['url'] = mb_convert_encoding( $array['url'], 'windows-1252', 'UTF-8' );

		if( preg_match( '#http://www.giaophanvinh.([a-z0-9]+)/uploads/Files/pub_dir/(.*)$#', $array['url'], $m ) )
		{
			$filename = basename( strtolower( $m[2] ) );
			$filename = explode( '.', $filename );
			$ext = array_pop( $filename );
			$filename = change_alias( implode( '-', $filename ) ) . '.' . $ext;
			if( nv_copyfile( NV_ROOTDIR . '/giaophanvinh.net/uploads/Files/pub_dir/' . $m[2], NV_UPLOADS_REAL_DIR . '/download/files/' . $filename ) )
			{
				$array['fileupload'] = '/download/files/' . $filename;
				$array['url'] = '';
			}

			// /home/vuthao/web/giaophanvinh.net.my/giaophanvinh.net/uploads/Files/pub_dir

			// home/vuthao/web/giaophanvinh.net.my/uploads/download/files
		}

		// http://www.giaophanvinh.net/uploads/Files/pub_dir/XD_mo_hinh_GH_tham_gia_de_thuc_thi_su_vu.doc

		$array['introtext'] = mb_convert_encoding( $array['introtext'], 'windows-1252', 'UTF-8' );
		$sql = "INSERT INTO `" . NV_PREFIXLANG . "_download`
				(`id`, `catid`, `title`, `alias`, `description`, `introtext`, `uploadtime`, `updatetime`,
				`user_id`, `user_name`, `author_name`, `author_email`, `author_url`,
				`fileupload`, `linkdirect`, `version`, `filesize`, `fileimage`,
				`status`, `copyright`, `view_hits`, `download_hits`,
			`groups_comment`, `groups_view`, `groups_download`, `comment_hits`, `rating_detail`)
	 		VALUES (
                " . $array['lid'] . ",
                " . $array['cid'] . ",
                " . $db->quote( $array['title'] ) . ",
                " . $db->quote( $alias ) . ",
                " . $db->quote( $array['description'] ) . ",
                " . $db->quote( $array['introtext'] ) . ",
                " . intval( $array['addtime'] ) . ",
                " . intval( $array['addtime'] ) . ",

                " . $db->quote( $array['user_id'] ) . ",
                " . $db->quote( $array['user_name'] ) . ",
                " . $db->quote( $array['name'] ) . ",
                " . $db->quote( $array['email'] ) . ",
                " . $db->quote( $array['homepage'] ) . ",

                " . $db->quote( $array['fileupload'] ) . ",
                " . $db->quote( $array['url'] ) . ",
                '',
                " . intval( $array['filesize'] ) . ",
                " . $db->quote( $array['fileimage'] ) . ",

                " . $db->quote( $array['status'] ) . ",
                " . $db->quote( $array['copyright'] ) . ",
                " . intval( $array['hits'] ) . ",
                " . intval( $array['hits'] ) . ",

                " . $db->quote( $array['groups_comment'] ) . ",
	            '6', '6', 0, '" . $array['rating_detail'] . "')";
		try
		{
			$db->query( $sql );
		}
		catch( PDOException $e )
		{
			print_r( $e );
			die();
		}

	}
}
die( "xong" );
