<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
use App\Http\Controllers\Controller;
use App\Models\RestLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

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
        $this->user = app('auth')->guard('api')->user();
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
    const CODE_NOT_ALLOWED                         = 200010;//禁止操作

    const CODE_ASSIGNMENT_STATUS_NOT_ALLOWED    =   200100; //委托状态不允许操作

    const CODE_FAIL_TO_SAVE_IMAGE =         200200;        //图片保存出错

    const CODE_ORDER_SAVE_ERROR = 200300;                 //订单生成失败


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
     * 禁止操作 统一返回格式
     * @param string $message
     * @return string
     */
    public static function notAllowed($message = "Operation Not Allowed")
    {
        return self::encodeResult(self::CODE_NOT_ALLOWED, $message);
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

    public function uploadFile(\Illuminate\Http\Request $request)
            {
                if($request->isMethod('post')){
                    $all = $request->all();
                    $rules = [
                        'upFile'=>'required',
            ];
            $messages = [
                'upFile.required'=>'请选择要上传的文件'
            ];
            $validator = Validator::make($all,$rules,$messages);
            if($validator->fails()){
                return back()->withErrors($validator);
            }
            //获取上传文件的大小
            $size = $request->file('upFile')->getSize();
            //这里可根据配置文件的设置，做得更灵活一点
            if($size > 2*1024*1024){
                return back()->with('errors','上传文件不能超过2M');
            }
            //文件类型
            $mimeType = $request->file('upFile')->getMimeType();
            //这里根据自己的需求进行修改
            if($mimeType != 'image/png'){
                return back()->with('errors','只能上传png格式的图片');
            }
            //扩展文件名
            $ext = $request->file('upFile')->getClientOriginalExtension();
            //判断文件是否是通过HTTP POST上传的
            $realPath = $request->file('upFile')->getRealPath();

            if(!$realPath){
                return back()->with('errors','非法操作');
            }

            //创建以当前日期命名的文件夹
            $today = date('Y-m-d');
            //storage_path().'/app/uploads/' 这里根据 /config/filesystems.php 文件里面的配置而定
            //$dir = str_replace('\\','/',storage_path().'/app/uploads/'.$today);
            $dir = storage_path().'/app/public/images/'.$today;
            if(!is_dir($dir)){
                mkdir($dir);
            }

            //上传文件
            $filename = uniqid().'.'.$ext;//新文件名
            if(Storage::disk('public')->put('/images/'.$today.'/'.$filename,file_get_contents($realPath))){
                return URL::asset("/images/$today/$filename");
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
}