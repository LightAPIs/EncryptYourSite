<?php

use Typecho\Plugin;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Encrypt Your Site
 *
 * @package EncryptYourSite
 * @author Light
 * @version 1.0.0
 * @link https://github.com/LightAPIs
 */
class EncryptYourSite_Plugin implements PluginInterface {
    private static $aesKey = 'typecho';
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate() {
        Plugin::factory('index.php')->begin = __CLASS__ . '::render';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate() {
        // do nothing
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        $siteTitle = Options::alloc()->title;
        $logoUrl = '/' . str_replace(Options::alloc()->siteUrl, "", Options::alloc()->pluginUrl) . '/EncryptYourSite/img/typecho-logo.png';
        $defaultHtml = <<<EOD
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="copyright" content="Light">
    <title>$siteTitle</title>
    <style>
        body {background: #467b96; color: #dae5ea; font-size: 18px; text-align: center; overflow: hidden;}
        .site_logo {display: block; width: 120px; text-indent: -9999em; background: url("$logoUrl") left center no-repeat; line-height: 50px;}
        h2, h3 {color: #fff; line-height: 1.1;}
        .container h2 {margin: 50px 0 10px; font-weight: 700; font-size: 36px;}
        .container h3 {margin: 25px 0 30px; font-weight: 400; font-size: 24px;}
        .post_form {display: flex; justify-content: center;}
        .post_form input[type="password"] {border: 1px solid #2196f3; border-radius: 3px; padding: 0 8px; width: 15em;}
        .post_form input[type="submit"] {background: #608ea5; border: 1px solid #608ea5; border-radius: 3px; color: #fff; cursor: pointer; margin-left: 5px; padding: 5px;}
    </style>
</head>
<body>
    <div class="container">
        <a href="https://typecho.org" target="_blank" class="site_logo" role="logo">Typecho</a>
        <h2>念念不忘，必有回响</h2>
        <h3>N 年 Typecho 沉淀，现在，回应您的等待</h3>
        <form class="post_form" action="?post_password" method="post">
            <input name="site_password" type="password" placeholder="请输入密码" />
            <input type="submit" value="提交验证" />
        </form>
    </div>
</body>
</html>
EOD;

        /** 页面 HTML */
        $html = new Textarea('html', null, $defaultHtml, _t('自定义密码页面的 HTML 内容'), _t('注意：页面上务必保留一个 name="site_password" 的 input 输入控件用于输入待验证的密码'));
        $form->addInput($html);

        $lifetime = new Radio('lifetime', array('0' => _t('会话期内'), '1' => _t('持久性的')), '1', _t('浏览器所保存密码的生命周期'), _t('会话期内：浏览器关闭之后密码会被自动删除；持久性的：生命周期取决于所指定的有效期'));
        $form->addInput($lifetime);

        $maxAge = new Text('maxAge', null, '30', _t('浏览器所保存密码的有效期'), _t('请填入数字，单位为：天。仅在将生命周期设置为持久性时生效。'));
        $form->addInput(($maxAge));

        $defaultPassword = 'password';
        /** 密码值 */
        $password = new Text('password', null, $defaultPassword, _t('请输入用于加密网站的密码'), _t('建议不要设定成与管理员及其他用户密码相同的密码'));
        $form->addInput($password);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
        // do nothing
    }

    /**
     * 实现方法
     */
    public static function render() {
        if (!strpos($_SERVER['REQUEST_URI'], 'index.php/action/')) {
            $savedPassword = Options::alloc()->plugin('EncryptYourSite')->password;
            if (isset($_POST['site_password'])) {
                if ($_POST['site_password'] == $savedPassword) {
                    $lifetime = Options::alloc()->plugin('EncryptYourSite')->lifetime;
                    if ($lifetime == '0') {
                        setcookie('site_password', self::encryptPasswordStr($savedPassword), 0, '/');
                    } else {
                        $maxAge = Options::alloc()->plugin('EncryptYourSite')->maxAge;
                        setcookie('site_password', self::encryptPasswordStr($savedPassword), time() + 60 * 60 * 24 * $maxAge, '/');
                    }
                    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['REQUEST_URI'] . '">';
                } else {
                    exit('<script type="text/javascript">alert("您输入的密码不正确，请重新输入"); location.href="' . $_SERVER['REQUEST_URI'] . '";</script>');
                }
            }

            if (empty($_COOKIE['site_password'])) {
                echo Options::alloc()->plugin('EncryptYourSite')->html;
                exit();
            } else if (!self::comparePassword($savedPassword, $_COOKIE['site_password'])) {
                //* 验证密码是否正确
                echo Options::alloc()->plugin('EncryptYourSite')->html;
                exit();
            }
        }
    }

    public static function encryptPasswordStr($passwd) {
        return urlencode(base64_encode(openssl_encrypt($passwd, 'AES-128-ECB', self::$aesKey, OPENSSL_RAW_DATA)));
    }

    public static function decryptPasswordStr($str) {
        return openssl_decrypt(base64_decode(urldecode($str)), 'AES-128-ECB', self::$aesKey, OPENSSL_RAW_DATA);
    }

    public static function comparePassword($passwd, $encryptStr) {
        $encodedPasswd = self::decryptPasswordStr($encryptStr);
        return strcmp($passwd, $encodedPasswd) == 0;
    }
}