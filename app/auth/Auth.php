<?php
/**
 * Created by PhpStorm.
 * User: Asus
 * Date: 2019/3/6
 * Time: 17:51
 */

namespace app\auth;

use im\core\redis\Redis;
use think\Db;

class Auth
{
	/**
	 * @var \Redis
	 */
	protected $redis;
	
	public function __construct()
	{
		$this->redis = new Redis();
	}
	
	/**
	 * 检测im登录token
	 * @param $token
	 * @param $type
	 * @param $authtype
	 * @return bool
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public function validateToken($token, $type, $authtype)
	{
		switch ($type) {
			case 'user':
				$user = Db::name('user')->where('token', $token)->field('user_id')->find();
				if ($user) {
					return $user['user_id'];
				} else {
					return false;
				}
				break;
			case 'account':
				$redis_cache_key_token = $token . $authtype;
				
				if ($this->redis->exists($redis_cache_key_token)) {
					//有此集合
					$data = $this->redis->zRangeByScore($redis_cache_key_token, time(), '+inf');
					$this->redis->zRemRangeByScore($redis_cache_key_token, 0, time());
					if (!$data) {
						//token过期
						return false;
					} else {
						return $data[0];
					}
				} else {
					$account_token = Db::name('account_token')->where('token', $token)
						->field('expire_time,account_id')
						->where('auth_type', $authtype)->find();
					if ($account_token) {
						//token是否过期
						if ($account_token['expire_time'] < time()) {
							return false;//token已过期，请重新登录
						}
						$this->redis->zAdd($redis_cache_key_token, $account_token['expire_time'], $account_token['account_id']);//加入set
						return $account_token['account_id'];
					} else {
						return false;//token无效，请重新登录
					}
				}
				break;
			default:
				return false;
		}
		return false;
	}
	
	
	/**
	 * roomkey生成算法
	 * @param $room
	 * @return string
	 */
	public function getRoomKey($room)
	{
		return 'imroom_' . md5($room);
	}
	
	/**
	 * 生成服务key
	 * @return string
	 */
	public function getServerKey()
	{
		return uniqid();
	}
	
	/**
	 * 未读消息队列key
	 * @return string
	 */
	public function get_unread_list_key()
	{
		return md5('unread_message');
	}
	
	/**
	 * 已读消息队列key
	 * @return string
	 */
	public function get_readed_list_key()
	{
		return md5('readed_message');
	}
	
	/**
	 * 消息详情key
	 * @param $msg_id
	 * @return string
	 */
	public function get_msg_detail_key($msg_id)
	{
		return md5('msg_detail_' . $msg_id);
	}
	
	/**
	 * 心跳key
	 * @param $user_id
	 * @return string
	 */
	public function get_ping_key($user_id)
	{
		return md5('_ping_key_' . $user_id);
	}
	
}