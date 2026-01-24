<?php
session_start();

// セッション変数をすべて解除する
$_SESSION = array();

// セッションクッキーを削除する
// クッキーは過去の有効期限を設定すると解除される
if (ini_get("session.use_cookies")) { $params = session_get_cookie_params(); setcookie(
	session_name(),
	'',
	time() - 42800,
	$params["path"],
	$params["domain"],
	$params["secure"],
	$params["httponly"]
);}

// セッションを破棄する
session_destroy();

// ログアウト後にログインページへリダイレクト
header("HTTP/1.1 303 See Other");
header("Location: ./login.php");
exit;
?>
