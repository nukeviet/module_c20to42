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
$module_data = 'news';
if( $step == 1 )
{
	try
	{
		$result = $db->query( "SELECT `catid` FROM `" . NV_PREFIXLANG . "_news_cat` ORDER BY `sort` ASC" );
		while( list( $catid_i ) = $result->fetch( 3 ) )
		{
			$db->query( "DROP TABLE IF EXISTS " . NV_PREFIXLANG . "_news_" . $catid_i );
		}

		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_cat`" );
		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_sources`" );
		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_topics`" );
		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_block`" );
		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_rows`" );
		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_tags`" );
		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_tags_id`" );

		nv_news_add_cat(); // Insert table _cat
		$nextstep++;
	}
	catch( PDOException $e )
	{
		print_r( $e );
		die();
	}
}
elseif( $step == 2 )
{
	nv_fix_cat_order(); // Sap xep cac chu de
	$nextstep++;
}
elseif( $step == 3 )
{
	nv_news_add_source(); // Tao Nguon tin
	$nextstep++;
}
elseif( $step == 4 )
{
	$content_no_save = nv_news_add_content(); // Chuyen bai viet
	if( !empty( $content_no_save ) )
	{
		print ( "Loi cac bai viet khong ghi duoc:<br>" . implode( "<br>", $content_no_save ) ) ;
		foreach( $db->query_strs as $key => $field )
		{
			if( empty( $field[1] ) )
			{
				print ( $field[0] ) ;
			}
		}
		die();
	}
	$nextstep++;
}
elseif( $step == 5 )
{
	nv_news_content_to_cat();
	$nextstep++;
}

if( $step <= 5 )
{
	$end_time = array_sum( explode( " ", microtime() ) );
	$total_time = substr( ( $end_time - NV_START_TIME + $db->time ), 0, 5 );

	$contents = "<br><br><center><font class=\"option\"><b>Dang thuc hien buoc " . $nextstep . " vui long doi</b></font></center>";
	$contents .= "<p align=\"center\">Thoi gian thuc hien buoc truoc: " . $total_time . "<br><br><img border=\"0\" src=\"" . NV_BASE_SITEURL . "images/load_bar.gif\"></p>";
	$contents .= "<META HTTP-EQUIV=\"refresh\" content=\"1;URL=" . NV_BASE_SITEURL . "news2.0.php?step=" . $nextstep . "&rand=" . nv_genpass() . "\">";
	die( $contents );
}

function nv_news_add_cat( $parentid = 0 )
{
	global $db, $prefix2, $lang_module, $lang_global, $op;

	list( $description, $keywords, $who_view, $groups_view ) = array(
		"",
		"",
		"",
		0,
		""
	);
	$viewcat = "viewcat_page_new";

	require_once NV_ROOTDIR . '/includes/action_mysql.php';

	$array_cat = array();
	$result = $db->query( "SELECT * FROM `" . $prefix2 . "_stories_cat` where parentid='" . $parentid . "'" );
	while( $rowcat = $result->fetch() )
	{
		$title = mb_convert_encoding( $rowcat['title'], 'windows-1252', 'UTF-8' );
		// $title = iconv( 'UTF-8', 'Windows-1252', $rowcat['title'] );
		$alias =  strtolower( change_alias( $title ) );
		if( empty( $alias ) )
		{
			die( "Dến đây: " . $rowcat['title'] );
		}
		else
		{
			$rowcat['title'] = $title;
		}

		try
		{
			$query = "INSERT INTO `" . NV_PREFIXLANG . "_news_cat` (`catid`, `parentid`, `title`, `alias`, `description`, `image`, `weight`, `sort`, `lev`, `viewcat`, `numsubcat`, `subcatid`, `inhome`, `numlinks`, `keywords`, `admins`, `add_time`, `edit_time`, `groups_view`)
        		 VALUES (" . $rowcat['catid'] . ", " . $rowcat['parentid'] . ", " . $db->quote( $rowcat['title'] ) . ", " . $db->quote( $alias ) . ", '', '" . $rowcat['catimage'] . "',  " . $rowcat['weight'] . ", '0', '0', " . $db->quote( $viewcat ) . ", '0', '', " . $rowcat['ihome'] . ", " . $rowcat['linkshome'] . ", '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '6')";
			$db->query( $query );
			nv_copy_structure_table( NV_PREFIXLANG . '_news_' . $rowcat['catid'], NV_PREFIXLANG . '_news_rows' );
		}
		catch( PDOException $e )
		{
			print_r( $e );
			die();
		}
		nv_news_add_cat( $rowcat['catid'] );
	}
	unset( $result, $array_cat, $rowcat );
}

/**
 * nv_fix_cat_order()
 *
 * @param integer $parentid
 * @param integer $order
 * @param integer $lev
 * @return
 *
 */
function nv_fix_cat_order( $parentid = 0, $order = 0, $lev = 0 )
{
	global $db, $module_data;

	$sql = 'SELECT catid, parentid FROM ' . NV_PREFIXLANG . '_' . $module_data . '_cat WHERE parentid=' . $parentid . ' ORDER BY weight ASC';
	$result = $db->query( $sql );
	$array_cat_order = array();
	while( $row = $result->fetch() )
	{
		$array_cat_order[] = $row['catid'];
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
		$sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . '_cat SET weight=' . $weight . ', sort=' . $order . ', lev=' . $lev . ' WHERE catid=' . intval( $catid_i );
		$db->query( $sql );
		$order = nv_fix_cat_order( $catid_i, $order, $lev );
	}
	$numsubcat = $weight;
	if( $parentid > 0 )
	{
		$sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . '_cat SET numsubcat=' . $numsubcat;
		if( $numsubcat == 0 )
		{
			$sql .= ",subcatid='', viewcat='viewcat_page_new'";
		}
		else
		{
			$sql .= ",subcatid='" . implode( ',', $array_cat_order ) . "'";
		}
		$sql .= ' WHERE catid=' . intval( $parentid );
		$db->query( $sql );
	}
	return $order;
}

function nv_news_add_source()
{
	global $db, $prefix2, $lang_module, $lang_global, $op;
	$weight = 0;

	$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_sources`" );
	$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_topics`" );

	$result = $db->query( "SELECT DISTINCT `source` FROM `" . $prefix2 . "_stories` WHERE source !=  ''" );
	while( list( $source ) = $result->fetch( 3 ) )
	{
		$weight++;
		$source = mb_convert_encoding( $source, 'windows-1252', 'UTF-8' );
		try
		{
			$query = "INSERT INTO `" . NV_PREFIXLANG . "_news_sources` (`sourceid`, `title`, `link`, `logo`, `weight`, `add_time`, `edit_time`) VALUES (NULL, " . $db->quote( $source ) . ", '', '', " . $weight . ", UNIX_TIMESTAMP( ), UNIX_TIMESTAMP( ))";
			$db->query( $query );
		}
		catch( PDOException $e )
		{
		}
	}
	unset( $result );

	$weight = 0;
	$result = $db->query( "SELECT `topicid`, `topictitle` FROM `" . $prefix2 . "_stories_topic` ORDER BY `topicid` ASC" );
	while( list( $topicid, $topictitle ) = $result->fetch( 3 ) )
	{
		$weight++;
		$topictitle = mb_convert_encoding( $topictitle, 'windows-1252', 'UTF-8' );
		$alias = strtolower( change_alias( $topictitle ) );
		try
		{
			$query = "INSERT INTO `" . NV_PREFIXLANG . "_news_topics` (`topicid`, `title`, `alias`, `description`, `image`,  `weight`, `keywords`, `add_time`, `edit_time`)
       			 VALUES (" . $topicid . ", " . $db->quote( $topictitle ) . ", " . $db->quote( $alias ) . ", '', '', " . $db->quote( $weight ) . ", '', UNIX_TIMESTAMP( ), UNIX_TIMESTAMP( ))";
			$db->query( $query );
		}
		catch( PDOException $e )
		{
			print_r( $e );
			die();
		}
	}

}

function nv_news_add_content()
{
	global $db, $prefix2, $lang_module, $lang_global, $op, $nv_Request, $module_config, $array_admin_id, $step;
	$array_source = $content_no_save = array();

	set_time_limit( 1200 );

	$sid = $nv_Request->get_int( 'sid', 'get', 0 );
	if( empty( $sid ) )
	{
		$db->query( "TRUNCATE TABLE `" . NV_PREFIXLANG . "_news_rows`" );
	}

	$result = $db->query( "SELECT sourceid, title FROM `" . NV_PREFIXLANG . "_news_sources`" );
	while( list( $sourceid, $title ) = $result->fetch( 3 ) )
	{
		$array_source[$title] = $sourceid;
	}
	$result = $db->query( "SELECT *, UNIX_TIMESTAMP(`time`) as addtime FROM `" . $prefix2 . "_stories` WHERE sid> " . $sid . " ORDER BY `sid` ASC LIMIT 1000" );
	$sid = 0;

	while( $rowcontent = $result->fetch() )
	{
		$rowcontent['homeimgfile'] = '';
		if( !empty( $rowcontent['images'] ) and file_exists( NV_ROOTDIR . '/website_old/uploads/News/pic/' . $rowcontent['images'] ) )
		{
			$dirimg = "";
			$month_upload_dir = nv_mkdir( NV_UPLOADS_REAL_DIR . '/news', date( "Y", $rowcontent['addtime'] ) ); // Thu muc uploads theo thang
			if( !empty( $month_upload_dir[0] ) and is_writable( NV_UPLOADS_REAL_DIR . '/news/' . date( "Y", $rowcontent['addtime'] ) ) )
			{
				$dirimg = date( "Y", $rowcontent['addtime'] ) . "/";
				nv_mkdir( NV_ROOTDIR . '/assets/news', date( "Y", $rowcontent['addtime'] ) ); // Thu muc uploads theo
			}

			$rowcontent['homeimgthumb'] = 0;
			if( nv_copyfile( NV_ROOTDIR . '/website_old/uploads/News/pic/' . $rowcontent['images'], NV_UPLOADS_REAL_DIR . '/news/' . $dirimg . $rowcontent['images'] ) )
			{
				$rowcontent['homeimgfile'] = $dirimg . $rowcontent['images'];

				$homeimgfile = NV_UPLOADS_REAL_DIR . "/news/" . $rowcontent['homeimgfile'];

				$basename = basename( $homeimgfile );
				$image = new image( $homeimgfile, NV_MAX_WIDTH, NV_MAX_HEIGHT );

				$image->resizeXY( $module_config['news']['homewidth'], $module_config['news']['homeheight'] );
				$image->save( NV_ROOTDIR . '/assets/news/' . $dirimg, $basename, 95 );
				$image_info = $image->create_Image_info;
				if( isset( $image_info['src'] ) and strpos( $image_info['src'], $rowcontent['homeimgfile'] ) )
				{
					$rowcontent['homeimgthumb'] = 1;
				}
				else
				{
					$rowcontent['homeimgthumb'] = 2;
				}

			}
		}

		$sid = $rowcontent['sid'];
		$source = $rowcontent['source'];

		$rowcontent['sourceid'] = ( isset( $array_source[$source] ) ) ? trim( $array_source[$source] ) : 0;
		$aid = trim( strtolower( $rowcontent['aid'] ) );
		if( ( isset( $array_admin_id[$aid] ) ) )
		{
			$rowcontent['admin_id'] = $array_admin_id[$aid];
			$rowcontent['author'] = "";
		}
		else
		{
			$rowcontent['author'] = $rowcontent['aid'];
			$rowcontent['admin_id'] = 0;
		}

		$rowcontent['title'] = mb_convert_encoding( $rowcontent['title'], 'windows-1252', 'UTF-8' );
		$rowcontent['hometext'] = mb_convert_encoding( $rowcontent['hometext'], 'windows-1252', 'UTF-8' );
		$rowcontent['author'] = mb_convert_encoding( $rowcontent['author'], 'windows-1252', 'UTF-8' );

		$rowcontent['edittime'] = $rowcontent['publtime'] = $rowcontent['addtime'];
		$rowcontent['alias'] = strtolower( change_alias( $rowcontent['title'] ) );
		$rowcontent['homeimgalt'] = mb_convert_encoding( $rowcontent['inhome'], 'windows-1252', 'UTF-8' );
		$rowcontent['inhome'] = $rowcontent['ihome'];
		$rowcontent['allowed_comm'] = ( empty( $rowcontent['acomm'] ) ) ? '6' : 0;

		$rowcontent['hitstotal'] = $rowcontent['counter'];
		$sql = "INSERT INTO `" . NV_PREFIXLANG . "_news_rows` (`id`, `catid`, `listcatid`, `topicid`, `admin_id`, `author`, `sourceid`, `addtime`, `edittime`, `status`, `publtime`, `exptime`, `archive`, `title`, `alias`, `hometext`, `homeimgfile`, `homeimgalt`, `homeimgthumb`, `inhome`, `allowed_comm`, `allowed_rating`, `hitstotal`, `hitscm`) VALUES
                (" . $rowcontent['sid'] . ",
                '" . $rowcontent['catid'] . "',
                '" . $rowcontent['catid'] . "',
                " . $rowcontent['topicid'] . ",
                " . intval( $rowcontent['admin_id'] ) . ",
                " . $db->quote( $rowcontent['author'] ) . ",
                " . $rowcontent['sourceid'] . ",
                " . $rowcontent['addtime'] . ",
                " . $rowcontent['edittime'] . ",
                1,
                " . $rowcontent['publtime'] . ",
                0,
                1,
                " . $db->quote( $rowcontent['title'] ) . ",
                " . $db->quote( $rowcontent['alias'] ) . ",
                " . $db->quote( $rowcontent['hometext'] ) . ",
                " . $db->quote( $rowcontent['homeimgfile'] ) . ",
                " . $db->quote( $rowcontent['homeimgalt'] ) . ",
                " . $db->quote( $rowcontent['homeimgthumb'] ) . ",
                " . $rowcontent['inhome'] . ",
                " . $rowcontent['allowed_comm'] . ",
                1,
                " . $rowcontent['hitstotal'] . ",
                0
                )
   			 ;";

		try
		{
			$db->query( $sql );
			$rowcontent['bodyhtml'] = mb_convert_encoding( $rowcontent['bodytext'], 'windows-1252', 'UTF-8' );
			$rowcontent['sourcetext'] = mb_convert_encoding( $rowcontent['source'], 'windows-1252', 'UTF-8' );

			// Get image tags
			$bodytext = $rowcontent['bodyhtml'];
			if( preg_match_all( "/\<img[^\>]*src=\"([^\"]*)\"[^\>]*\>/is", $bodytext, $match ) )
			{
				foreach( $match[0] as $key => $_m )
				{
					$textimg = '';
					if( strpos( $match[1][$key], 'data:image/png;base64' ) === false )
					{
						$textimg = " " . $match[1][$key];
					}
					if( preg_match_all( "/\<img[^\>]*alt=\"([^\"]+)\"[^\>]*\>/is", $_m, $m_alt ) )
					{
						$textimg .= " " . $m_alt[1][0];
					}
					$bodytext = str_replace( $_m, $textimg, $bodytext );
				}
			}
			// Get link tags
			if( preg_match_all( "/\<a[^\>]*href=\"([^\"]+)\"[^\>]*\>(.*)\<\/a\>/isU", $bodytext, $match ) )
			{
				foreach( $match[0] as $key => $_m )
				{
					$bodytext = str_replace( $_m, $match[1][$key] . " " . $match[2][$key], $bodytext );
				}
			}

			$bodytext = str_replace( '&nbsp;', ' ', strip_tags( $bodytext ) );
			$bodytext = preg_replace( '/[ ]+/', ' ', $bodytext );

			$rowcontent['imgposition'] = 1;
			$rowcontent['copyright'] = 0;
			$rowcontent['allowed_send'] = 1;
			$rowcontent['allowed_print'] = 1;
			$rowcontent['allowed_save'] = 1;
			$rowcontent['gid'] = 0;

			$stmt = $db->prepare('INSERT INTO ' . NV_PREFIXLANG . '_news_detail (`id`, `titlesite`, `description`, `bodyhtml`, `sourcetext`, `imgposition`, `copyright`, `allowed_send`, `allowed_print`, `allowed_save`, `gid`) VALUES
					(' . $rowcontent['sid'] . ',
					' . $db->quote($rowcontent['post_title']) . ',
					 ' . $db->quote(nv_clean60($hometext, 200, true)) . ',
					 ' . $db->quote($rowcontent['bodyhtml']) . ',
					 ' . $db->quote($rowcontent['hometext']) . ',
					 1,
					 1,
					 1,
					 1,
					 1,
					 0
					 )');
			$stmt->execute();

		}
		catch( PDOException $e )
		{
			print_r( $e );
			die();
		}
	}

	if( $sid )
	{
		$contents = "<br><br><center><font class=\"option\"><b>Dang thuc hien buoc " . $step . " vui long doi</b></font></center>";
		$contents .= "<p align=\"center\">Thoi gian thuc hien buoc truoc: " . $total_time . "<br><br><img border=\"0\" src=\"" . NV_BASE_SITEURL . "assets/images/load_bar.gif\"></p>";
		$contents .= "<META HTTP-EQUIV=\"refresh\" content=\"1;URL=" . NV_BASE_SITEURL . "news2.0.php?step=" . $step . "&sid=" . $sid . "&rand=" . nv_genpass() . "\">";
		die( $contents );
	}
	else
	{
		$query = "SELECT `catid`, `parentid` FROM `" . NV_PREFIXLANG . "_news_cat` where `parentid` > 0";
		$result = $db->query( $query );
		$array_cat_order = array();
		while( list( $catid, $parentid ) = $result->fetch( 3 ) )
		{
			$db->query( "UPDATE `" . NV_PREFIXLANG . "_news_rows` SET `listcatid` = '" . $parentid . "," . $catid . "' WHERE `listcatid` ='" . $catid . "'" );
		}
	}
}

function nv_news_content_to_cat()
{
	global $db;

	$result = $db->query( "SELECT id, listcatid FROM `" . NV_PREFIXLANG . "_news_rows` ORDER BY `id` ASC" );
	while( list( $id, $listcatid ) = $result->fetch( 3 ) )
	{
		$arr_catid = explode( ",", $listcatid );
		foreach( $arr_catid as $catid )
		{
			try
			{
				$db->query( "INSERT INTO `" . NV_PREFIXLANG . "_news_" . $catid . "` SELECT * FROM `" . NV_PREFIXLANG . "_news_rows` WHERE `id`=" . $id . "" );
			}
			catch( PDOException $e )
			{

			}
		}
	}
}
die( "xong" );