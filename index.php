<?php
/*
Plugin Name: Blog Old Post XML-RPC
Description: 過去の記事をランダムにXML-RPCに対応した外部ブログに投稿
Author: @woodroots
Version: 1.0
Author URL: http://wood-roots.com
*/


require_once 'XML/RPC.php';
require_once 'FC2BlogManager.php';

//多次元配列を綺麗にする
function array_filter_recursive($input) 
{ 
    foreach ($input as &$value) { 
    
        if (is_array($value)) { 
            $value = array_filter_recursive($value); 
        }

    } 
    
    return array_filter($input); 
} 

//投稿する処理
function blog_old_post_submit($title, $text, $endpoint,$user_id,$pass) {

	$host = substr($endpoint,0,strpos($endpoint,'/'));
	$xmlrpc_path = str_replace($host,'',$endpoint);
	
	
	//ログ用
	$mes = '';
	
	try {
		$bm = new FC2BlogManager($host, $xmlrpc_path);
		$bm->setUser($user_id);
		$bm->setPassword($pass);
		$bm->postEntry($title, $text);
		//ログ用
		$mes .= date( "Y/m/d (D) H:i:s", time() ) . 'に実行されました：<br />' . "\n";
		$mes .= print_r($bm,true) . "\n";
	} catch(Exception $e) {

		$mes .= date( "Y/m/d (D) H:i:s", time() ) . 'に実行されましたがエラーでした：<br />' . "\n";
		$mes .= print_r($e,true) . "\n";
		
	}
	
	return $mes;
}

//実行
function blog_old_post(){
	//設定値の取得
	/*
	blog_old_post_settings内には
	array(
		array(
			user_id => aaaa,
			pass => bbb,
			endpoint => cccc/xmlrpc
		).
		array(
			user_id => aaaa,
			pass => bbb,
			endpoint => cccc/xmlrpc
		).
	);
	と入っている
	*/
	
	$settings = get_option('blog_old_post_settings');
	
	if($settings){
		//投稿処理
		$mes = '';
		foreach($settings as $val){

			//記事の取得
			$post = get_posts(array(
				'numberposts' => 1,
				'orderby' => 'rand'
			));
			$post = $post[0];

				//投稿用記事生成
				$title = $post->post_title ? $post->post_title : 'タイトルなし';
				$title = apply_filters('blog_old_post_title',$title,$post);
				$text = '<div class="content">' . $post->post_content . '</div>';
				$text = apply_filters('blog_old_post_content',$text,$post);

				//ここで投稿
				$mes .= blog_old_post_submit($title, $text, $val['endpoint'],$val['user_id'],$val['pass']);
		}
		update_option('blog_old_post_log',$mes);
	}
}

//Cron登録
if(get_option('blog_old_post_interval')){
add_filter('cron_schedules','blog_old_post_time');
function blog_old_post_time($schedules){
	$blog_post_interval = intval(get_option('blog_old_post_interval'));

	$schedules['blogpost'] = array(
		'interval' => $blog_post_interval*60,
		'display' => __( 'blogpost' )
	);
	return $schedules;
}

add_action('blog_old_post_cron', 'blog_old_post');
function blog_old_post_setcron() {
	if ( !wp_next_scheduled( 'blog_old_post_cron' ) ) {
		wp_schedule_event(time(), 'blogpost', 'blog_old_post_cron');
	}
}
add_action('wp', 'blog_old_post_setcron');
}


//管理画面生成
function blog_old_post_menu(){
	add_menu_page(
	'Blog Old Post XML-RPC',
	'Blog Old Post XML-RPC',
	'administrator',
	'blog_old_post_menu',
	'blog_old_post_setting'
	);
}

//メニュー
add_action('admin_menu','blog_old_post_menu');


//IDとPASS設定画面
function blog_old_post_setting(){
	if(isset($_POST["submit"]) && wp_verify_nonce( $_POST['_wpnonce'], 'blog_old_post')){
		update_option('blog_old_post_interval',intval($_POST['blog_old_post_interval']));
		update_option('blog_old_post_settings',array_filter_recursive($_POST['blog_old_post_settings']));
		
		wp_clear_scheduled_hook('blog_old_post_cron');
		wp_schedule_event(time(), 'blogpost', 'blog_old_post_cron');
	}

	echo '
		<style type="text/css">
			.btn {
				margin-top: 20px;
			}
			.taright {
				text-align: right;
			}
			.blog_old_post_table {
				width: 100%;
			}
			.blog_old_post_table th {
				width: 150px;
				text-align: center;
				background: #eee;
			}
			.blog_old_post_table th,.blog_old_post_table td {
				border: 1px solid #ccc;
			}
			
		</style>
		<script type="text/javascript">
			(function($){
				var nameset = function(){
					$(".blog_old_post_table").each(function(key,value){
						$(this).find("input").attr("name",$(this).attr("name").replace(/\[[0-9]*?\]/,"["+key+"]"));
					});
				}
				$(function(){
					nameset();
					$("#add_form").on("click",function(){
						obj = $(".blog_old_post_table:last-of-type").clone();
						$(this).parent().before(obj);
						nameset();
						return false;
					});
					
				});
			})(jQuery);
		</script>
			
		<h2>Blog Old Post XML-RPC設定画面</h2>';
		
		if(isset($_POST["submit"]) && wp_verify_nonce( $_POST['_wpnonce'], 'blog_old_post')){
		echo '<div class="updated fade" id="message">
				<p>設定が更新されました。</p>
			</div>';
		};
		
		echo '<h3>ランダム投稿設定</h3>
		<form action="" method="post">
			<input type="hidden" name="_wpnonce" value="'.wp_create_nonce('blog_old_post').'" />
			<div>
				<input type="text" name="blog_old_post_interval" value="'.htmlspecialchars(get_option('blog_old_post_interval')).'" />分おきに投稿する
			</div>
			<div class="btn"><input type="submit" name="submit" class="button-primary" value="この内容で登録" /></div>
		';
		
		echo '<h3>ID・パスワード・エンドポイント設定</h3>';
		
		$settings = get_option('blog_old_post_settings');
		
		if($settings){
		foreach($settings as $key=>$val){
			echo '
			<table class="blog_old_post_table">
				<tr>
					<th>ID</th>
					<td><input name="blog_old_post_settings[1][user_id]" type="text" value="'.htmlspecialchars($val[user_id]).'" /></td>
				</tr>
				<tr>
					<th>パスワード</th>
					<td><input name="blog_old_post_settings[1][pass]" type="text" value="'.htmlspecialchars($val[pass]).'" /></td>
				</tr>
				<tr>
					<th>エンドポイント</th>
					<td>http://<input name="blog_old_post_settings[1][endpoint]" type="text" value="'.htmlspecialchars($val[endpoint]).'" /></td>
				</tr>
			</table>
			';
		}
		}//endif
		
			echo '
			<table class="blog_old_post_table">
				<tr>
					<th>ID</th>
					<td><input name="blog_old_post_settings[2][user_id]" type="text" value="" /></td>
				</tr>
				<tr>
					<th>パスワード</th>
					<td><input name="blog_old_post_settings[2][pass]" type="text" value="" /></td>
				</tr>
				<tr>
					<th>エンドポイント</th>
					<td>http://<input name="blog_old_post_settings[2][endpoint]" type="text" value="" /></td>
				</tr>
			</table>
			';

		
		echo '
			<div class="btn taright"><button id="add_form">入力フォームの追加</button></div>
			<div class="btn"><input type="submit" name="submit" class="button-primary" value="この内容で登録" /></div>
		</form>
		<h2>前回実行時のログ</h2>
		';
		echo '<pre>'.get_option('blog_old_post_log').'</pre>';
}
//delete_option('blog_old_post_settings');

?>