<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
use App\Http\Controllers\Controller;
use App\Models\RestLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/10/30
 * Time: 13:46
 */

class BaseController extends Controller
{
    protected  $user;

    public function __construct()
    {
        $this->user = app('auth')->guard('api')->user();;
    }

    /**
     * 当前版本号
     */
    const VERSION = '1.0.0';

    /**
     * 版本号所在路由位置
     */
    const VERSION_SEGMENT_INDEX = 4;

    //通用的消息代码
    const CODE_SUCCESS                         = 100000;//成功
    const CODE_PARAM_ILLEGAL                 = 200003;//参数不合法，必填的参数没有传入，或类型不合法
    const CODE_NOT_FUND_RESOURCE              = 200009;//请求的资源不存在（资源 404）

    /**
     * 获取请求所要求的版本号
     * @return int|string
     */
    protected function getWantsVersion()
    {
        //获取路由中的版本号
        $routeVersion = \Request::segment(self::VERSION_SEGMENT_INDEX, "");
        $routeVersion = ltrim($routeVersion, "v");

        //获取头部的版本号
        $headerVersion = \Request::header('version', $routeVersion);
        $version = (is_numeric($headerVersion) && $headerVersion > $routeVersion) ? $headerVersion : $routeVersion;

        return $version;
    }

    /**
     * 找不到资源 统一返回格式
     * @param string $message
     * @return string
     */
    public static function resourceNotFound($message = "Not Found Resource")
    {
        return self::encodeResult(self::CODE_NOT_FUND_RESOURCE, $message);
    }

    /**
     * 参数不合法 统一返回格式
     * @param null $message
     * @return string
     */
    public static function parametersIllegal($message = null)
    {
        return self::encodeResult(self::CODE_PARAM_ILLEGAL, $message);
    }


    /**
     * 正确的返回统一格式
     * @param $data
     * @return string
     */
    public static function success($data = null)
    {
        return self::encodeResult(self::CODE_SUCCESS, 'success', $data);
    }

    /**
     * 错误的返回统一格式
     * @param $data
     * @return string
     */
    public static function error($code, $message = null, $data = null)
    {
        return self::encodeResult($code, $message, $data);
    }

    /**
     * 统一返回格式
     * @param      $msgcode
     * @param null $message
     * @param null $data
     * @return string
     */
    public static function encodeResult($msgcode, $message = null, $data = null)
    {
        if ($data == null) {
            $data = new \stdClass();
        }

        $log = new RestLog();
        $log->request = json_encode(Request::except('file'));
        $log->request_route = \Route::currentRouteName();
        $log->response = json_encode($data);
        $log->msgcode = $msgcode;
        $log->message = $message;
        $log->client_ip = Request::getClientIp();
        $log->client_useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        $log->save();

        $result = [
            "request_id" => $log->id,
            'msgcode'    => $msgcode,
            'message'    => $message,
            'response'   => $data,
            'version'    => self::VERSION,
            'next_step'  => '',
            'servertime' => time()
        ];

        return \Response::json($result);
    }
}