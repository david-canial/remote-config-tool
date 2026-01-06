<?php

namespace DavidCanial\RemoteConfigTool;

use Google\Auth\ApplicationDefaultCredentials;

class FirebaseAuth
{
    /**
     * Firebase Remote Config 用のアクセストークンを取得します。
     *
     * Google Cloud の Application Default Credentials を利用して
     * service account からトークンをフェッチします。
     *
     * @return string アクセストークン（Bearer トークン本体）
     *
     * @throws \RuntimeException アクセストークンが取得できなかった場合
     */
    public static function getAccessToken(): string
    {
        // Firebase Remote Config API に必要なスコープ
        $scopes = ["https://www.googleapis.com/auth/firebase.remoteconfig"];

        // Application Default Credentials から資格情報を取得
        $credentials = ApplicationDefaultCredentials::getCredentials($scopes);
        $token = $credentials->fetchAuthToken();

        if (empty($token["access_token"])) {
            throw new \RuntimeException("Google のアクセストークンを取得できませんでした。");
        }

        return $token["access_token"];
    }
}
