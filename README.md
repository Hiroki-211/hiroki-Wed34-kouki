# 手順書

## 1. DockerおよびDockerComposeのインストール

### サーバー上で以下のコマンドを実行し、Dockerをインストール

```sh
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker
```
 
### デフォルトのユーザー(ec2-user)でもsudoをつけずにdockerコマンドを実行できるように、dockerグループに追加

```sh
sudo usermod -aG docker ec2-user
```

usermodを反映するために一度ログアウトする必要があります。  
sshの場合は一度ログアウトしログインしなおすことで反映させることができます。
  　
### Docker Composeのインストール

```sh
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
```

インストールできたかの確認

```sh
docker compose version
```

## 2. Git インストール

```sh
sudo yum install git -y
```

## 3. ソースコードの配置

```sh
git clone https://github.com/Hiroki-211/hiroki-Wed34-kouki.git
```

## 4. ビルド＆起動

```sh
cd hiroki-Wed34-kouki
```

### docker composeをビルド・起動する

```sh
docker compose build
docker compose up
```

## 5. テーブルの作成

別のターミナルで以下のコマンドを実行し、Dockerコンテナ内のmysqlサーバーにmysqlコマンドで接続する

```sh
docker compose exec mysql mysql example_db 
```

#### 会員テーブル

```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    icon_filename VARCHAR(255) DEFAULT NULL,
    introduction TEXT DEFAULT NULL,
    cover_filename VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### 投稿テーブル

```sql
CREATE TABLE posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    content TEXT,
    image_filename1 VARCHAR(255) DEFAULT NULL,
    image_filename2 VARCHAR(255) DEFAULT NULL,
    image_filename3 VARCHAR(255) DEFAULT NULL,
    image_filename4 VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### フォロー関係テーブル

```sql
CREATE TABLE user_relationships (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    followee_user_id INT UNSIGNED NOT NULL,
    follower_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (followee_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (follower_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_user_id, followee_user_id)
);
```

上記のコマンドで起動できたら、ウェブブラウザでEC2インスタンスのホスト名またはIPアドレス(SSHでログインするときと同じもの)に接続する。  

ブラウザのURLに`http://IPアドレス/timeline.php`と入力して開いてみる
ログイン画面にリダイレクトし、ログイン後タイムラインが表示されたら成功。
