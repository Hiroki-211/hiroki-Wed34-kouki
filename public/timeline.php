<?php
session_start();
if (empty($_SESSION['login_user_id'])) {  // 非ログインの場合利用不可
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  exit;  // return; から exit; に変更
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// ログイン中のユーザー
$login_user_id = $_SESSION['login_user_id'];
// ログイン中のユーザー情報を取得
$user_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$user_sth->execute([':id' => $login_user_id]);
$user = $user_sth->fetch(PDO::FETCH_ASSOC);

// 投稿処理
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])){

  $image_filename = null;
  if (!empty($_POST['image_base64'])) {
    // 先頭の data:~base64, のところは削る
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

    // base64からバイナリにデコードする
    $image_binary = base64_decode($base64);

    // 新しいファイル名を決めてバイナリを出力する
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
    $filepath = '/var/www/upload/image/' . $image_filename;
    file_put_contents($filepath, $image_binary);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO posts (user_id, content, image_filename1) VALUES (:user_id, :content, :image_filename1)");
  $insert_sth->execute([
    ':user_id' => $_SESSION['login_user_id'], // ログインしている会員情報の主キー
    ':content' => $_POST['body'], // フォームから送られてきた投稿本文
    ':image_filename1' => $image_filename,  // 保存した画像の名前（nullの場合もある）
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと、リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 303 See Other");
  header("Location: ./timeline.php");  // location から Location に変更
  exit;  // return; から exit; に変更
}

// LIMITとOFFSETを指定
$limit = max(5, min(50, (int)($_GET['limit'] ?? 20)));  // 上限を50に変更
$offset = max(0, (int)($_GET['offset'] ?? 0));

// 投稿データを取得
// フォローしている人の投稿と自分自身の投稿のみ表示
$sql = 'SELECT posts.*, users.username AS user_name, users.icon_filename AS user_icon_filename'
    . ' FROM posts'
    . ' INNER JOIN users ON posts.user_id = users.id'
    . ' LEFT OUTER JOIN user_relationships ON posts.user_id = user_relationships.followee_user_id'
    . ' WHERE user_relationships.follower_user_id = :login_user_id OR posts.user_id = :login_user_id'
    . ' ORDER BY posts.created_at DESC'
    . ' LIMIT :limit OFFSET :offset';

$select_sth = $dbh->prepare($sql);

$select_sth->bindValue(':login_user_id', $_SESSION['login_user_id'], PDO::PARAM_INT);
$select_sth->bindValue(':limit', $limit, PDO::PARAM_INT);
$select_sth->bindValue(':offset', $offset, PDO::PARAM_INT);

$select_sth->execute();
$posts = $select_sth->fetchAll(PDO::FETCH_ASSOC);

// ajax=1の場合はJSONを返して終了（HTMLは出力しない）
if (!empty($_GET['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($posts);
  exit;
}

// bodyのHTMLを出力するための関数を用意する
function bodyFilter (?string $body): string  // ← string から ?string に変更（null対応）
{
  if ($body === null || $body === '') {
    return '';
  }
  
  $body = htmlspecialchars($body); // エスケープ処理
  $body = nl2br($body); // 改行文字を<br>要素に変換

  // >>1といった文字列を該当番号の投稿へのページ内リンクとする（レスアンカー機能）
  // 「>」（半角の大なり記号）はhtmlspecialchars()でエスケープされているため注意
  $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);

  return $body;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>タイムライン</title>
</head>
<body>
    <!-- ヘッダー部分 -->
    <div>
      現在 <?= htmlspecialchars($user['username']) ?> (ID: <?= htmlspecialchars($user['id']) ?>)さんでログイン中
    </div>
    
    <!-- 投稿フォーム -->
    <form method="POST" action="./timeline.php">
      <textarea name="body" required></textarea>
      <div style="margin: 1em 0;">
        <input type="file" accept="image/*" name="image" id="imageInput">
      </div>
      <input id="imageBase64Input" type="hidden" name="image_base64">
      <canvas id="imageCanvas" style="display: none;"></canvas>
      <button type="submit">送信</button>
    </form>
    <hr>

    <!-- 投稿を表示するコンテナ（JavaScriptで描画される） -->
    <div id="posts"></div>
    
    <!-- スクロール監視用の要素 -->
    <div id="sentinel" style="height: 1px;"></div>

    <script>
    // 無限スクロール
    const limit = 20;
    let offset = 0;
    let loading = false;
    let allLoaded = false;

    async function fetchPosts() {
      if (loading || allLoaded) return;
      loading = true;
      const res = await fetch(`/timeline.php?ajax=1&offset=${offset}&limit=${limit}`);
      const data = await res.json();
      if (!Array.isArray(data) || data.length === 0) {
        allLoaded = true;
        loading = false;
        return;
      }
      renderPosts(data);
      offset += data.length;
      loading = false;
    }

    function renderPosts(list) {
      const container = document.getElementById('posts');
      if (!container) return;
      
      list.forEach(p => {
        const postElement = document.createElement('dl');
        postElement.style.marginBottom = '1em';
        postElement.style.paddingBottom = '1em';
        postElement.style.borderBottom = '1px solid #ccc';
        
        postElement.innerHTML = `
          <dt id="entry${p.id}">番号</dt>
          <dd>${escapeHtml(p.id)}</dd>
          <dt>投稿者</dt>
          <dd>
            <a href="/profile.php?user_id=${p.user_id}">
              ${p.user_icon_filename ? `<img src="/upload/image/${escapeHtml(p.user_icon_filename)}" style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">` : ''}
              ${escapeHtml(p.user_name)} (ID: ${escapeHtml(p.user_id)})
            </a>
          </dd>
          <dt>日時</dt>
          <dd>${escapeHtml(p.created_at)}</dd>
          <dt>内容</dt>
          <dd>
            ${escapeHtml(p.content ?? '')}
            ${p.image_filename1 ? `<div><img src="/upload/image/${escapeHtml(p.image_filename1)}" style="max-height: 10em;"></div>` : ''}
          </dd>
        `;
        container.appendChild(postElement);
      });
    }

    function escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, m =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])
      );
    }

    // DOMContentLoadedで実行
    document.addEventListener('DOMContentLoaded', function() {
      const sentinel = document.getElementById('sentinel');
      
      if (!sentinel) {
        console.error('sentinel要素が見つかりません');
        return;
      }
      
      const io = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) fetchPosts();
      });
      
      io.observe(sentinel);
      
      // 初回ロード
      fetchPosts();
    });
    </script>
</body>
</html>
