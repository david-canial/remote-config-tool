## david-canial/remote-config-tool

**Firebase Remote Config を操作するための PHP クライアントライブラリ**です。

- Google の service account を使って **アクセストークンを取得**。
- **Remote Config の取得**（ETag 付き）。
- **ETag を使った更新** および **force update** をサポート。

シンプルなスクリプト、バッチ処理、既存バックエンドへの組み込みなどに適しています。

---

## インストール

### 必要環境

- PHP 8.0+
- Composer
- Firebase プロジェクトと、`Firebase Remote Config Admin` 権限を持つ service account

Composer でインストールします:

```bash
composer require david-canial/remote-config-tool
```

---

## service account と credentials の準備

1. **Google Cloud Console** を開きます:
   - 「IAM と管理」→「サービス アカウント」
   - 新しい service account を作成（既存のものを使っても OK）

2. 権限（ロール）の付与:
   - Firebase Remote Config を操作できるロールを付与します。例:
     - `Firebase Remote Config Admin`
     - もしくは同等の権限を含むカスタムロール

3. JSON キーを作成:
   - service account 詳細画面 → 「キー」タブ → 「キーを追加」→「新しいキーを作成」→ JSON
   - ダウンロードした JSON ファイルを、例えば `/path/to/service-account.json` に保存

4. Google 認証用の環境変数を設定:

```bash
export GOOGLE_APPLICATION_CREDENTIALS="/path/to/service-account.json"
```

5. `FIREBASE_PROJECT_ID` を設定:

```bash
export FIREBASE_PROJECT_ID="your-firebase-project-id"
```

`your-firebase-project-id` は、JSON ファイル内の `projectId` か、Firebase コンソールのプロジェクト ID に対応します。

> Laravel や Symfony などのフレームワークを利用している場合は、これらの値を `.env` に定義し、フレームワーク側で読み込ませても構いません。

---

## 静的ファサード `RemoteConfig` を使った簡単な利用例

インストールと環境変数の設定が完了したら、`RemoteConfig` ファサードを利用できます:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use DavidCanial\RemoteConfigTool\RemoteConfig;

// アクセストークンを取得
$token = RemoteConfig::token();

// Remote Config（ETag 付き）を取得
$result = RemoteConfig::get();
$etag   = $result['etag'];
$config = $result['data'];

// 現在の ETag のみ取得
$etagOnly = RemoteConfig::etag();

// Remote Config の本体データのみ（ETag なし）
$dataOnly = RemoteConfig::config();
```

環境変数 `FIREBASE_PROJECT_ID` を使わず、明示的に projectId を指定することもできます:

```php
$etag = RemoteConfig::etag('your-firebase-project-id');
```

---

## ETag を使った Remote Config の更新

Firebase Remote Config では、他の更新を誤って上書きしないように、更新時に **ETag** を送信することが推奨されています。

`parameters` を一部変更する例:

```php
use DavidCanial\RemoteConfigTool\RemoteConfig;

// 現在の設定を取得
$result = RemoteConfig::get();
$etag   = $result['etag'];
$config = $result['data'];

// 例: 特定の parameter を変更したい場合
$parameters = $config['parameters'] ?? [];

$parameters['example_param'] = [
    'defaultValue' => ['value' => '123'],
];

// 古い ETag を指定して Firebase に送信
$updateResult = RemoteConfig::update(
    ['parameters' => $parameters],
    $etag
);

$newEtag = $updateResult['etag'] ?? null;
```

もし ETag が古い場合（他のクライアントが先に更新していた場合など）、Firebase は HTTP 412 (Precondition Failed) を返します。その場合は:

- `RemoteConfig::get()` で最新の ETag と設定を再取得する
- 自分の変更をその最新設定に再適用してから、再度 update する

---

## Force update（ETag を無視して上書き）

状況によっては、**ETag チェックを無視して常に現在の設定を上書きしたい** 場合もあります:

```php
use DavidCanial\RemoteConfigTool\RemoteConfig;

// 新しい parameters を用意
$parameters = [
    'example_param' => [
        'defaultValue' => ['value' => 'force-update'],
    ],
];

$result = RemoteConfig::forceUpdate(['parameters' => $parameters]);
$newEtag = $result['etag'] ?? null;
```

> 注意: `forceUpdate` は他のクライアントによる変更を上書きする可能性があります。同時更新があり得る環境では慎重に使用してください。

---

## `RemoteConfigClient` を直接利用する

より細かく制御したい場合や、独自の Guzzle クライアントを注入したい場合は、`RemoteConfigClient` クラスを直接利用できます:

```php
use DavidCanial\RemoteConfigTool\RemoteConfigClient;
use GuzzleHttp\Client as HttpClient;

// 環境変数を利用してクライアントを生成
$client = new RemoteConfigClient();

// あるいは projectId と Guzzle クライアントを明示的に指定
//$http   = new HttpClient(['timeout' => 30]);
//$client = new RemoteConfigClient('your-firebase-project-id', $http);

// アクセストークンを取得
$token = $client->getToken();

// Remote Config を取得
$result = $client->get();
$etag   = $result['etag'];
$config = $result['data'];
```

---

## `.env` を使った設定読み込み（任意）

このライブラリは、`vlucas/phpdotenv` を利用して `.env` を読み込み、
その上で `RemoteConfigClient` インスタンスを生成するヘルパーも提供しています:

```php
use DavidCanial\RemoteConfigTool\RemoteConfigClient;

// 例: プロジェクトルートに .env がある場合
// .env
// FIREBASE_PROJECT_ID=your-firebase-project-id
// GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json

$client = RemoteConfigClient::createFromEnvFile(__DIR__ . '/..');

$result = $client->get();
```

`createFromEnvFile($directory, $file = '.env')` は次のことを行います:

- 指定ディレクトリと `.env` ファイルの存在チェック
- `.env` 内の環境変数を読み込み
- 読み込んだ環境変数を元に `RemoteConfigClient` インスタンスを生成

---

## エラー処理

よくあるエラーとその原因:

- **`GOOGLE_APPLICATION_CREDENTIALS` が未設定またはパスが誤っている**:
  - `google/auth` ライブラリが、credentials を見つけられない場合に例外を投げます。
  - JSON ファイルのパスと、読み取り権限を確認してください。

- **`FIREBASE_PROJECT_ID` が未設定**:
  - `RemoteConfigClient` は `RuntimeException` を投げます。そのメッセージは:
    - `"FIREBASE_PROJECT_ID が設定されていません。RemoteConfigClient のコンストラクタに projectId を渡すか、環境変数 FIREBASE_PROJECT_ID を設定してください。"`

- **Firebase からの 4xx / 5xx エラー**:
  - 資格情報不足、ETag の不整合、ペイロードフォーマットの不備などが原因で、Guzzle の HTTP 例外としてスローされます。
  - `\GuzzleHttp\Exception\ClientException` をキャッチすることで、レスポンスボディを確認できます。

基本的なエラーハンドリング例:

```php
use DavidCanial\RemoteConfigTool\RemoteConfig;
use GuzzleHttp\Exception\ClientException;

try {
    $result = RemoteConfig::get();
} catch (ClientException $e) {
    $response = $e->getResponse();
    $status   = $response->getStatusCode();
    $body     = (string) $response->getBody();

    // Firebase から返ってきたエラー内容をログに出すなどの処理
} catch (\Throwable $e) {
    // 環境変数の不足、ネットワークエラー、JSON パースエラーなど
}
```

---

## ライセンス

このパッケージは **MIT ライセンス** のもとで提供されます。商用・個人プロジェクトを問わず自由に利用できます。



# remote-config-tool
# remote-config-tool
# remote-config-tool
