<?php
/**
 * ProSupl - Backend
 * © 2020 Václav Maroušek
 *
 * sos.php
 */

namespace OpenSOS;

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
     * @param User $user
     */
    public function SaveSession(User $user): void {
        //Save session info
        $user->SetMeta('SOS_SESSION_COOKIES', json_encode($this->requester->GetCookies()));
        $user->SetMeta('SOS_SESSION_KEY',     $this->rsa->getPublicKey());
        $user->SetMeta('SOS_SESSION_TIME',    time());
    }

    /**
     * @param User $user
     * @return bool
     */
    public function LoadSession(User $user): bool {
        //Check session life
        $sessionTime = $user->GetMeta('SOS_SESSION_TIME');

        if (!empty($sessionTime) AND time() < ($sessionTime + SOS_SESSION_LIFETIME)) {
            $sessionCookies = json_decode($user->GetMeta('SOS_SESSION_COOKIES'), true);
            $sessionKey = $user->GetMeta('SOS_SESSION_KEY');

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

        return json_decode($this->requester->Request(SOS_URI . 'login.php?nocache=' . time() .'&function=' . urlencode($function), METHOD_POST, $post), true);
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

        return json_decode($this->requester->Request(SOS_URI . 'classification.php?nocache=' . time() .'&function=' . urlencode($function), METHOD_POST, $post), true);
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

        return json_decode($this->requester->Request(SOS_URI . 'inout.php?nocache=' . time() .'&function=' . urlencode($function), METHOD_POST, $post), true);
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

        return json_decode($this->requester->Request(SOS_URI . 'st.php?nocache=' . time() .'&function=' . urlencode($function), METHOD_POST, $post), true);
    }

    /**
     * Retrieves attendance
     * @param $function
     * @param $data
     * @return array
     */
    public function Tp($function, $data): array {
        $function = $this->Encrypt($function);

        $post = "&data=" . json_encode($data);

        return json_decode($this->requester->Request(SOS_URI . 'tp.php?nocache=' . time() .'&function=' . urlencode($function), METHOD_POST, $post), true);
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

        return json_decode($this->requester->Request(SOS_URI . 'info.php?nocache=' . time() .'&function=' . urlencode($function), METHOD_POST, $post), true);
    }

    /**
     * Classbook requests, useless for students
     * @param $function
     * @param $data
     * @return array
     */
    public function Classbook($function, $data): array {
        $function = $this->Encrypt($function);

        $post = "&data=" . json_encode($data);

        return json_decode($this->requester->Request(SOS_URI . 'classbook.php?nocache=' . time() .'&function=' . urlencode($function), METHOD_POST, $post), true);
    }
}