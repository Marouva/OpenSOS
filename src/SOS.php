<?php
/**
 * ProSupl - Backend
 * © 2020 Václav Maroušek
 *
 * sos.php
 */

namespace OpenSOS;

use Crypt_RSA;
use Math_BigInteger;

define('SOS_URI', 'https://is.sps-prosek.cz/');
define('SOS_SESSION_LIFETIME', 1920); //32 minutes

class SOS {
    /** @var Requester $requester */
    public $requester;

    /** @var Crypt_RSA $rsa */
    private $rsa;

    public function __construct() {
        $this->requester = new Requester();
        $this->requester->SetRequestHeader('X-Qooxdoo-Response-Type', 'application/json');

        $this->rsa = new Crypt_RSA();
    }

    public function SetKey($e, $n) {
        $exponent = new Math_BigInteger('0x' . $e, 16);
        $nonce = new Math_BigInteger('0x00' . $n, 16);

        $publicKey = ["e" => $exponent, "n" => $nonce];

        $this->rsa->loadKey($publicKey, CRYPT_RSA_PUBLIC_FORMAT_RAW);
        $this->rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
    }

    public function Encrypt($message) {
        return chunk_split(implode(unpack("H*", $this->rsa->encrypt($message))),64,"\n");
    }

    /**
     * @return array
     */
    public function SaveSession(): array {
        return [
            'SOS_SESSION_COOKIES' => json_encode($this->requester->GetCookies()),
            'SOS_SESSION_KEY' => $this->rsa->getPublicKey(),
            'SOS_SESSION_TIME' => time()
        ];
    }

    /**
     * @param array $data
     * @return bool
     */
    public function LoadSession(Array $data): bool {
        //Check session life
        $sessionTime = $data['SOS_SESSION_TIME'];

        if (!empty($sessionTime) AND time() < ($sessionTime + SOS_SESSION_LIFETIME)) {
            $sessionCookies = json_decode($data['SOS_SESSION_COOKIES'], true);
            $sessionKey = $data['SOS_SESSION_KEY'];

            if (!empty($sessionCookies) AND !empty($sessionKey)) {
                //Load session info
                $this->requester->SetCookies($sessionCookies);
                $this->rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
                $this->rsa->loadKey($sessionKey);

                //The key was successfully loaded and is probably ok
                return true;
            } else {
                //Key or cookie is not present
                return false;
            }
        } else {
            //Session has expired or has not been initialised
            return false;
        }
    }

    // ----------------------------------------------------------------
    // Helper functions
    // ----------------------------------------------------------------

    public function UserLogin($username, $password) {
        return $this->Login('userlogin', [
            'username' => $username,
            'password' => $this->Encrypt($password)
        ]);
    }

    public function MyClassification() {
        return $this->Classification('myclassification', [
            'type' => 'student'
        ]);
    }

    public function GetTopicAttendance($subjectId) {
        return $this->St('gettopicattendance', [
            'subject' => $subjectId
        ]);
    }

    public function UserInout($startDate, $endDate) {
        return $this->Inout('user', [
            'from' => $startDate,
            'to' => $endDate
        ]);
    }

    // ----------------------------------------------------------------
    // API functions
    // ----------------------------------------------------------------

    /**
     * Start session
     */
    public function Start(): void {
        $session = json_decode($this->requester->Request(SOS_URI . '6NpSdyj2TJw45LYb.php'), true);

        //Set retrieved public key
        $this->SetKey($session["e"], $session["n"]);
    }

    /**
     * Attempt login using given credentials
     * @param $function
     * @param $data
     * @return array
     */
    public function Login($function, $data): array {
        $function = $this->Encrypt($function);

        $post = '&data=' . json_encode($data);

        return json_decode($this->requester->Request(SOS_URI . 'login.php?nocache=' . time() .'&function=' . urlencode($function), 'POST', $post), true);
    }

    /**
     * Logout
     * @return array
     */
    public function Logout(): array {
        return json_decode($this->requester->Request(SOS_URI . 'logout.php'), true);
    }

    /**
     * Retrieves classification
     * @param $function
     * @param $data
     * @return array
     */
    public function Classification($function, $data): array {
        $function = $this->Encrypt($function);

        $post = "&data=" . json_encode($data);

        return json_decode($this->requester->Request(SOS_URI . 'classification.php?nocache=' . time() .'&function=' . urlencode($function), 'POST', $post), true);
    }

    /**
     * Retrieves entries
     * @param $function
     * @param $data
     * @return array
     */
    public function Inout($function, $data): array {
        $function = $this->Encrypt($function);

        $post = "&data=" . json_encode($data);

        return json_decode($this->requester->Request(SOS_URI . 'inout.php?nocache=' . time() .'&function=' . urlencode($function), 'POST', $post), true);
    }

    /**
     * Retrieves attendance
     * @param $function
     * @param $data
     * @return array
     */
    public function St($function, $data): array {
        $function = $this->Encrypt($function);

        $post = "&data=" . json_encode($data);

        return json_decode($this->requester->Request(SOS_URI . 'st.php?nocache=' . time() .'&function=' . urlencode($function), 'POST', $post), true);
    }

    /**
     * Retrieve info about a user given their id (isic card)
     * @param $function
     * @param $data
     * @return array
     */
    public function Info($function, $data): array {
        $function = $this->Encrypt($function);

        $post = "&data=" . json_encode($data);

        return json_decode($this->requester->Request(SOS_URI . 'info.php?nocache=' . time() .'&function=' . urlencode($function), 'POST', $post), true);
    }
}