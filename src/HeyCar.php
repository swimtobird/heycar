<?php
/**
 *
 * User: swimtobird
 * Date: 2021-04-12
 * Email: <swimtobird@gmail.com>
 */

namespace Swimtobird\Heycar;


use GuzzleHttp\Client;

class HeyCar
{
    /**
     * @var Client
     */
    protected $client;

    protected $key;

    protected $secret;

    protected $is_dev = false;

    const DEV_URL = 'http://test-now.heycars.cn/api';

    const PROD_URL = 'http://now.heycars.cn/api';

    public function __construct(string $key,string $secret,$is_dev=false)
    {
        $this->client = new Client();

        $this->key = $key;

        $this->secret = $secret;

        $this->setDev($is_dev);
    }

    /**
     * @param bool $is_dev
     * @return $this
     */
    public function setDev(bool $is_dev)
    {
        $this->is_dev = $is_dev;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDev()
    {
        return $this->is_dev;
    }

    /**
     * @return string
     */
    public function getURl(): string
    {
        if ($this->is_dev) {
            return self::DEV_URL;
        } else {
            return self::PROD_URL;
        }
    }

    /**
     * @param $url
     * @param array $data
     * @param string $token
     * @return array
     * @throws RequestException
     */
    protected function request($url, array $data = [], string $token = '')
    {
        $data = array_merge($data, [
            'reqSid'  => md5(uniqid('', false)),
            'reqFrom' => 'app',
        ]);

        $data['sign'] = $this->getSign($data);

        $request = [
            'json' => $data
        ];

        if (!empty($token)) {
            $request['headers'] = [
                'token' => $token
            ];
        }

        $response = $this->client->post($this->getURl() . $url,$request );

        if ($response->getStatusCode() !== 200) {
            throw new RequestException('Heycar server is exception!');
        }

        $result = json_decode($response->getBody(), true);

        if (isset($result['code']) && $result['code'] !== 200) {
            throw new RequestException($result['msg']??'', $result['code']);
        }

        return $result;
    }

    protected function formatData(array $data)
    {
        if (!empty($data)){
            foreach ($data as $key => $value){
                if (is_array($value)){
                    $data[$key] = json_encode($value);
                }
            }
        }

        return $data;
    }
    protected function getSign(array $data): string
    {
        $data = array_merge($data, [
            'signKey' => $this->secret,
        ]);

        ksort($data);

        $result = '';

        foreach ($data as $key => $value) {
            if ($value){
                if (!is_array($value)) {
                    if (is_bool($value)){
                        $value = $value?'true':'false';
                    }
                    $result .= $key . $value;
                }
                if (is_array($value) && array_values($value) === $value){
                    $result .= $key . json_encode($value);
                }
            }
        }
        return sha1($result);
    }

    /**
     * 获取接口授权token
     * @param $phone
     * @return array
     * @throws RequestException
     */
    public function getToken($phone): array
    {
        return $this->request('/login/getToken', [
            'channelKey' => $this->key,
            'contactPhone' => $phone,
            'timeStamp' => (string)time()
        ]);
    }

    /**
     * 查询城市列表
     * @param string $token
     * @return array
     * @throws RequestException
     */
    public function getCityList(string $token)
    {
        return $this->request('/platform/queryCityList', [], $token);
    }

    /**
     * 获取估价
     * @param string $token
     * @param int $booking_type
     * @param array $departs
     * @param array $arrives
     * @param bool $use_time
     * @return array
     * @throws RequestException
     */
    public function getValuation(string $token, int $booking_type, array $departs, array $arrives, $use_time = false)
    {
        $data = [
            'bookingType'   => $booking_type,
            'departAddress' => $departs,
            'arriveAddress' => $arrives,
        ];
        if ($use_time) {
            $data['useTime'] = $use_time;
        }
        return $this->request('/platform/queryProductList', $data, $token);
    }

    /**
     * @param string $token
     * @param array $data
     * @return array
     * @throws RequestException
     */
    public function createOrder(string $token, array $data)
    {
        return $this->request('/platform/createOrder', $data, $token);
    }

    /**
     * 获取订单详情
     * @param string $token
     * @param string $order_sn
     * @return array
     * @throws RequestException
     */
    public function getOrder(string $token, string $order_sn)
    {
        return $this->request('/platform/queryOrderDetail', ['orderId' => $order_sn], $token);
    }

    /**
     * 取消订单
     * @param string $token
     * @param string $order_sn
     * @return array
     * @throws RequestException
     */
    public function cancelOrder(string $token, string $order_sn)
    {
        return $this->request('/platform/cancelOrder', [
            'orderId' => $order_sn,
            'force'   => true
        ], $token);
    }

    /**
     * 订单列表
     * @param string $token
     * @param int $page
     * @param int $size
     * @param array $data
     * @return array
     * @throws RequestException
     */
    public function getOrderList(string $token, int $page, int $size, array $data = [])
    {
        $data['page'] = $page;
        $data['size'] = $size;

        return $this->request('/platform/queryOrderList', $data, $token);
    }

    /**
     * 获取司机实时位置
     * @param string $token
     * @param string $order_sn
     * @return array
     * @throws RequestException
     */
    public function getDriverLocation(string $token,string $order_sn)
    {
        return $this->request('/platform/queryDriverLocation', ['orderId' => $order_sn], $token);
    }

    /**
     * 司机评分
     * @param string $token
     * @param string $order_sn
     * @param int $score
     * @param string $comment
     * @return array
     * @throws RequestException
     */
    public function saveOrderScore(string $token,string $order_sn,int $score,string $comment='')
    {
        return $this->request('/platform/orderScore', [
            'orderId' => $order_sn,
            'level' => $score,
            'comment' => $comment
        ], $token);
    }

    /**
     * 获取可投诉原因列表
     * @param string $token
     * @param string $order_sn
     * @return array
     * @throws RequestException
     */
    public function getComplainReasons(string $token,string $order_sn)
    {
        return $this->request('/platform/complain', [
            'orderId' => $order_sn,
        ], $token);
    }
    /**
     * 乘客发起投诉
     * @param string $token
     * @param string $order_sn
     * @param int $reason_id
     * @param string $content
     * @return array
     * @throws RequestException
     */
    public function complain(string $token,string $order_sn,int $reason_id,string $content='')
    {
        return $this->request('/platform/complain', [
            'orderId' => $order_sn,
            'type' => $reason_id,
            'content' => $content
        ], $token);
    }

    /**
     * 添加企业员工
     * @param string $token
     * @param array $data
     * @return array
     * @throws RequestException
     */
    public function addEmployee(string $token,array $data)
    {
        return $this->request('/platform/complain', $data, $token);
    }

    /**
     * 移除企业员工
     * @param string $token
     * @param string $user_id
     * @param string $channel_id
     * @return array
     * @throws RequestException
     */
    public function removeEmployee(string $token,string $user_id,string $channel_id)
    {
        return $this->request('/common/removeCompanyMembers', [
            'channelId' => $channel_id,
            'userId' => $user_id
        ], $token);
    }

    /**
     * 修改企业员工
     * @param string $token
     * @param string $user_id
     * @param string $channel_id
     * @param array $data
     * @return array
     * @throws RequestException
     */
    public function updateEmployee(string $token,string $user_id,string $channel_id,array $data)
    {
        $data['channelId'] = $channel_id;
        $data['userId'] = $user_id;

        return $this->request('/platform/complain', $data, $token);
    }

    /**
     * 申请单同步
     * @param string $token
     * @param array $data
     * @return array
     * @throws RequestException
     */
    public function addBusinessTravelForm(string $token,array $data)
    {
        return $this->request('/common/businessTravelForm', $data, $token);
    }

    /**
     * 批量添加企业员工
     * @param string $token
     * @param array $data
     * @return array
     * @throws RequestException
     */
    public function addEmployees(string $token,array $data)
    {
        $result['employees'] = $data;

        return $this->request('/common/addListCompanyMembers', $result, $token);
    }
}