<?php
/**
 * @class FC2BlogManager
 */
class FC2BlogManager {
    /**
     * デフォルトポート番号
     */
    const DEFAULT_PORT = 80;
    
    /**
     * デフォルトエンコーディング（XML-RPCのエンコード)
     */
    const DEFAULT_ENCODING = "utf-8";
    
    /**
     * ブログ取得
     */
    const COMMAND_GETBLOG = "blogger.getUsersBlogs";

    /**
     * エントリーの投稿
     */
    const COMMAND_POSTENTRY = "metaWeblog.newPost";

    /**
     * エンコード(XML-RPCのエンコード)
     */
    protected $encoding = self::DEFAULT_ENCODING;

    /**
     * ポート番号
     */
    protected $port;

    /**
     * ホスト名
     */
    protected $hostname;

    /**
     * スクリプト名
     */
    protected $script;

    /**
     * ユーザー名
     */
    protected $user;

    /**
     * パスワード
     */
    protected $password;

    /**
     * XML-RPCクライアント
     */
    protected $client;

    /**
     * デバッグモード
     */
    protected $debug;

    /**
     * コンストラクタ
     * @param String $hostname ホスト名
     * @param String $script スクリプト名
     * @param Integer $port [ポート番号]
     */
    public function __construct($hostname, $script,
                                $port = self::DEFAULT_PORT){
        $this->setHostname($hostname);
        $this->setScript($script);
        $this->setPort($port);
        $this->client =
            new XML_RPC_client($this->script, $this->hostname, $this->port);
    }

    /**
     * Setters
     */
    public function setUser($user){
        $this->user = $user;
    }
    public function setPassword($password){
        $this->password = $password;
    }
    public function setDebugMode($debug){
        $this->debug = $debug;
    }
    public function setEncoding($encoding){
        $this->encoding = $encoding;
    }
    protected function setPort($port){
        $this->port = $port;
    }
    protected function setHostname($hostname){
        $this->hostname = $hostname;
    }
    protected function setScript($script){
        $this->script = $script;
    }

    /**
     * エントリーの投稿
     * @param String $title 投稿するエントリーのタイトル
     * @param String $content 投稿するエントリーのコンテンツ
     * @return Integer 投稿したエントリのID
     */
public function postEntry($title, $content, $blogid = 0){
    if ($blogid === 0) {
        $blogid = new XML_RPC_Value( 0, 'string');
    } else if ($blogid === '') {
        $blogid = new XML_RPC_Value( '', 'string');
    } else {
        $blogid = new XML_RPC_Value( $blogid, 'string');
    }
    $username = $this->createStringValue($this->user);
    $passwd = $this->createStringValue($this->password);
    $content = new XML_RPC_Value(
        array(
            'title'=> $this->createStringValue($title),
            'description'=> $this->createStringValue($content),
            'dateCreated'=>
                new XML_RPC_Value(date("Ymd\TH:i:s", time()),
                                  'dateTime.iso8601')),
        'struct');
    $publish = new XML_RPC_Value(1, 'boolean');
    $message = new XML_RPC_Message(
                   self::COMMAND_POSTENTRY,
                   array($blogid, $username, $passwd, $content, $publish));
     
    $result = $this->sendMessage($message);
    return $result;
}
    /**
     * ブログの取得
     * @return Array ブログの情報
     */
    public function getBlogs(){
        $appkey = new XML_RPC_Value( '', 'string' );
        $username = new XML_RPC_Value($this->user, 'string' );
        $passwd = new XML_RPC_Value($this->password, 'string' );
        
        $message =
            new XML_RPC_Message(self::COMMAND_GETBLOG,
                                array($appkey, $username, $passwd) );
        
        $result = $this->sendMessage($message);
        return $result;
    }

    /**
     * RPCメッセージの送信
     * @param XML_RPC_Message $message RPCメッセージ
     * @return Array 文字コードが正規化されたサーバーからの応答．
     */
    protected function sendMessage($message){
        if($this->debug){
            echo "メッセージ送信<br>\n";
            var_dump($message);
        }
        $result = $this->client->send($message);
        
        if(!$result){
            throw new Exception('Could not connect to the server. ' . $this->hostname . ":" . $this->script);
        }else if( $result->faultCode() ){
            throw new Exception('XML-RPC fault ('.$result->faultCode().'): '
                                .$result->faultString());
        }

        return $this->decodeRPCResult($result);
    }

    /**
     * 文字コードを変換してXML_RPC_Valueを作る
     * @param String string 対象の文字列
     * @return XML_RPC_Value 作ったValue
     */
    protected function createStringValue($string){
        $string = $this->convertEncoding($string, $this->encoding,
                                         mb_internal_encoding());
        return new XML_RPC_Value($string, "string");
    }

    /**
     * サーバー応答の文字コードを正規化する関数
     * @param XML_RPC_Response $result サーバー応答
     * @return Array 文字コードが正規化された配列
     */
    protected function decodeRPCResult($result){
        $decoded = XML_RPC_decode($result->value());
        return $this->convertEncodingRecursively($decoded,
                                                 mb_internal_encoding(),
                                                 $this->encoding);
    }

    /**
     * 文字コードを再帰的に変換する
     * @param Misc $data 対象のデータ
     * @param String $to 変換後のエンコード
     * @param String $from 変換前のエンコード
     * @return Misc 変換されたデータ
     */
    protected function convertEncodingRecursively($data, $to, $from){
        if(is_array($data)){
            foreach($data as $key => $datum){
                $data[$key] =
                    $this->convertEncodingRecursively($datum, $to, $from);
            }
        }else if(is_string($data)){
            return $this->convertEncoding($data, $to, $from);
        }
        return $data;
    }
        
    /**
     * 文字列の文字コードを変換する
     * @param String $data 対象の文字列
     * @param String $to 変換後のエンコード
     * @param String $from 変換前のエンコード
     * @return String 変換された文字列
     */
    protected function convertEncoding($data, $to, $from){
        return mb_convert_encoding($data, $to, $from);
    }
}
?>
