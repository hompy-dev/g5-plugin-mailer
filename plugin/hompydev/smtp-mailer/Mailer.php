<?php

/**
 * Gnuboard5 Mailer Plugin (g5-plugin-mailer)
 * @see https://github.com/hompy-dev/g5-plugin-mailer
 * @author <contact@hompy.dev>
 * @license https://github.com/hompy-dev/g5-plugin-mailer/blob/main/LICENSE (MIT License)
 */

declare(strict_types=1);

namespace HompyDev\G5Plugin\Mailer;

if (!defined('_GNUBOARD_') || PHP_VERSION_ID < 70000) {
    return;
}

class Mailer
{
    // 클래스 객체
    private static $instance = null;

    // 설정 정보
    private $userEnv = [];

    // 관리자모드 메뉴 번호 (기존 사용하는 번호 중복시 미사용 번호로 변경)
    private const ADMIN_MENU_CODE = '100305';

    // 설정 저장 파일 ('.env' 로 시작되는 파일명 필수)
    private const ENV_FILE = G5_DATA_PATH . '/.env.hompydev.mailer';

    // php extension 'imap' 사용 가능 상태 (보낸편지함 저장 사용시 필수, https://www.php.net/manual/en/book.imap.php)
    private $imapEnable = false;

    // 이메일 공급자 기본정보
    private $provider = [
        'naver' => [
            'smtp_host'    => 'smtp.naver.com',
            'smtp_port'    => '587',
            'smtp_secure'  => 'tls',
            'imap_host'    => 'imap.naver.com',
            'imap_port'    => '993',
            'imap_sentbox' => 'Sent Messages'
        ],
        'daum' => [
            'smtp_host'    => 'smtp.daum.net',
            'smtp_port'    => '465',
            'smtp_secure'  => 'ssl',
            'imap_host'    => 'imap.daum.net',
            'imap_port'    => '993',
            'imap_sentbox' => 'Sent Messages'
        ],
        'nate' => [
            'smtp_host'    => 'smtp.mail.nate.com',
            'smtp_port'    => '587',
            'smtp_secure'  => 'tls',
            'imap_host'    => 'imap.nate.com',
            'imap_port'    => '993',
            'imap_sentbox' => 'Sent Messages'
        ],
        'gmail' => [
            'smtp_host'    => 'smtp.gmail.com',
            'smtp_port'    => '465',
            'smtp_secure'  => 'ssl',
            'imap_host'    => 'imap.gmail.com',
            'imap_port'    => '993',
            'imap_sentbox' => '[Gmail]/&vPSwuNO4ycDVaA-'
        ]
    ];

    // 설정 정보 적용
    public function __construct()
    {
        $this->userEnv = $this->getEnv();
        $this->imapEnable = extension_loaded('imap');
    }

    // 클래스 객체 반환
    public static function getInstance(): Mailer
    {
        return (!self::$instance) ? new self() : self::$instance;
    }

    // 설정 정보 반환
    public function getEnv(): array
    {
        if (!is_file(self::ENV_FILE)) {
            return $this->setEnv(['provider' => '', 'ip' => $_SERVER['REMOTE_ADDR'], 'updated_at' => G5_TIME_YMDHIS]);
        } else {
            $json = $this->cryption('dec', file_get_contents(self::ENV_FILE));
            return json_decode($json, true);
        }
    }

    // 설정 정보 저장
    public function setEnv(array $env = []): array
    {
        $encrypted = $this->cryption('enc', json_encode($env, JSON_UNESCAPED_UNICODE));

        $f = fopen(self::ENV_FILE, 'w');
        fwrite($f, $encrypted . "\n");
        fclose($f);

        chmod(self::ENV_FILE, G5_FILE_PERMISSION);

        $this->userEnv = $env;

        return $env;
    }

    // 설정 데이터 암복호화 ($mode enc|dec)
    public function cryption(string $mode, string $text)
    {
        $result = false;

        if ($mode == 'enc') {
            $result = base64_encode(openssl_encrypt($text, 'aes-256-cbc', G5_TOKEN_ENCRYPTION_KEY, OPENSSL_RAW_DATA, str_repeat(chr(0), 16)));
        } elseif ($mode == 'dec') {
            $result = openssl_decrypt(base64_decode($text), 'aes-256-cbc', G5_TOKEN_ENCRYPTION_KEY, OPENSSL_RAW_DATA, str_repeat(chr(0), 16));
        }

        return $result;
    }

    // 관리자모드 메뉴 추가
    public function add_admin_menu(array $admin_menu): array
    {
        $menu100 = [];

        foreach ($admin_menu['menu100'] as $k => $arr) {
            if ($arr[0] == '100300') {
                $menu100[] = array('100305', '메일설정 (SMTP)', G5_ADMIN_URL . '/view.php?call=smtp_config', 'smtp_config', 1);
            }
            $menu100[] = $arr;
        }
        $admin_menu['menu100'] = $menu100;

        return $admin_menu;
    }

    // 관리자모드 설정 페이지
    public function admin_page_smtp_config(array $arr_query, string $token): void
    {
        global $is_admin, $auth, $config;

        $smtpConfig = $this->getEnv();
        $smtpConfig = \array_map_deep('trim', $smtpConfig);
        $smtpConfig = \array_map_deep('get_sanitize_input', $smtpConfig);
        $smtpConfig['from_email'] = $smtpConfig['from_email'] ?? \get_sanitize_input($config['cf_admin_email']);
        $smtpConfig['from_name'] = $smtpConfig['from_name'] ?? \get_sanitize_input($config['cf_admin_email_name']);

        include __DIR__ . '/page.admin.config.php';
    }

    // 관리자모드 설정 정보 업데이트
    public function smtp_config_update(): void
    {
        //  submit form action
        if ($_POST['poster'] == 'smpt_config' && isset($_POST['token'])) {
            if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                exit;
            }
            header('Content-Type: application/json');

            $errorMessage = \auth_check_menu($auth, self::ADMIN_MENU_CODE, 'w', true);
            if ($errorMessage) {
                $json = json_encode(['result' => 'fail', 'message' => $errorMessage, 'value' => ''], JSON_UNESCAPED_UNICODE);
                exit($json);
            }

            unset($_POST['token'], $_POST['poster']);
            $_POST = array_map_deep('trim', $_POST);
            $_POST = array_map_deep('get_sanitize_input', $_POST);

            if (!$_POST['provider']) { // 사용 안 함으로 저장
                $post['provider'] = '';
            } else { // 외부계정 정보 저장
                $post = $_POST;
            }
            $post['ip'] = $_SERVER['REMOTE_ADDR'];
            $post['updated_at'] = G5_TIME_YMDHIS;

            $storedJson = $this->setEnv($post);

            $resultArray = [
                'result' => 'success',
                'message' => '저장 되었습니다.',
                'value' => ''
            ];

            exit(json_encode($resultArray, JSON_UNESCAPED_UNICODE));
        }
    }

    // 메일 발송
    public function mailer($fname, $fmail, $to, $subject, $content, $type=0, $file="", $cc="", $bcc="")
    {
        // 사용 안 함
        if (!$this->userEnv['provider']) {
            return;
        }
        $result['return'] = false;

        $provider = $this->provider[$this->userEnv['provider']];

        $mail = new \PHPMailer();
        $mail->IsSMTP();
        $mail->Host = $provider['smtp_host'];
        $mail->Port = $provider['smtp_port'];
        $mail->Username = $this->userEnv['account_id'];
        $mail->Password = $this->userEnv['account_pw'];
        $mail->SMTPSecure = $provider['smtp_secure'];
        $mail->SMTPAutoTLS = false;
        $mail->SMTPAuth = true;
        $mail->CharSet = 'UTF-8';
        $mail->From = $this->userEnv['from_email'];
        $mail->FromName = $this->userEnv['from_name'];
        $mail->Subject = $subject;
        $mail->AltBody = '';
        $mail->msgHTML($content);
        $mail->addAddress($to);
        if ($cc) {
            $mail->addCC($cc);
        }
        if ($bcc) {
            $mail->addBCC($bcc);
        }
        if ($file != '') {
            foreach ($file as $f) {
                $mail->addAttachment($f['path'], $f['name']);
            }
        }

        // 메일 테스트인 경우 debuging
        if (preg_match('~\/sendmail_test\.php$~i', $_SERVER['SCRIPT_NAME'])) {
            // $mail->SMTPDebug = 2;
        }

        // 발송
        $result['return'] = $mail->send();

        // 보낸편지함 전송 (IMAP: Sent Messages)
        if ($result['return'] && $this->userEnv['save_sentmail'] && $this->imapEnable) {
            $path = '{' . $provider['imap_host'] . ':' . $provider['imap_port'] . '/imap/ssl}' . $provider['imap_sentbox'];
            $stream = imap_open($path, $mail->Username, $mail->Password) or die("can't connect: " . imap_last_error());
            $imapResult = imap_append($stream, $path, $mail->getSentMIMEMessage());
            imap_close($stream);
            // $err = imap_errors();
            // var_dump($err);
        }

       return $result;
    }

    // IMAP 메일함 목록
    public function mailboxes(): array
    {
        if (!$this->imapEnable) {
            return [];
        }

        $provider = $this->provider[$this->userEnv['provider']];
        $path = '{' . $provider['imap_host'] . ':' . $provider['imap_port'] . '/imap/ssl}';
        $stream = imap_open($path, $this->userEnv['account_id'], $this->userEnv['account_pw']) or die("can't connect: " . imap_last_error());
        $boxes = imap_getmailboxes($stream, $path, "*");
        $boxesMakeup = [];

        if (is_array($boxes)) {
            foreach ($boxes as $k => $v) {
                $boxesMakeup[$k] = $v->name;
                $name_utf8 = imap_mutf7_to_utf8($v->name);
                if ($boxesMakeup[$k] != $name_utf8) {
                    $name_utf8 = str_replace($path, '', $name_utf8);
                    $boxesMakeup[$k] .= ' (' . $name_utf8 . ')';
                }
            }
        }
        imap_close($stream);

        return $boxesMakeup;
    }

    // G5 Hooks 등록
    public static function hooks(): void
    {
        // 관리자모드 메뉴 추가
        \add_replace('admin_menu', [self::getInstance(), 'add_admin_menu'], 1, 1);

        // 관리자모드 페이지 추가
        \add_event('admin_get_page_smtp_config', [self::getInstance(), 'admin_page_smtp_config'], 1, 2);

        // 관리자모드 SMTP 설정 저장
        \add_event('admin_request_handler_smtp_config', [self::getInstance(), 'smtp_config_update'], 1, 0);

        // 메일 발송 (replace function mailer : /lib/mailer.lib.php)
        \add_replace('mailer', [self::getInstance(), 'mailer'], 10, 9);
    }
}

Mailer::hooks();
