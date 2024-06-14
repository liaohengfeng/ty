<?php

/* 获取文章列表 已测试 √  */
function _getPost($self)
{
	$self->response->setStatus(200);
	$page = $self->request->page;
	$pageSize = $self->request->pageSize;
	$type = $self->request->type;

	/* sql注入校验 */
	if (!preg_match('/^\d+$/', $page)) {
		return $self->response->throwJson(array("data" => "非法请求！已屏蔽！"));
	}
	if (!preg_match('/^\d+$/', $pageSize)) {
		return $self->response->throwJson(array("data" => "非法请求！已屏蔽！"));
	}
	if (!preg_match('/^[created|views|commentsNum|agree]+$/', $type)) {
		return $self->response->throwJson(array("data" => "非法请求！已屏蔽！"));
	}

	/* 如果传入0，强制赋值1 */
	if ($page == 0) $page = 1;
	$result = [];
	/* 增加置顶文章功能，通过JS判断（如果你想添加其他标签的话，请先看置顶如何实现的） */
	$sticky_text = Helper::options()->JIndexSticky;
	if ($sticky_text && $page == 1) {
		$sticky_arr = explode("||", $sticky_text);
		foreach ($sticky_arr as $cid) {
			$self->widget('Widget_Contents_Post@' . $cid, 'cid=' . $cid)->to($item);
			if ($item->next()) {
				$result[] = array(
					"mode" => $item->fields->mode ? $item->fields->mode : 'default',
					"image" => joe\getThumbnails($item),
					"time" => date('Y-m-d', $item->created),
					"created" => date('Y年m月d日', $item->created),
					"title" => $item->title,
					"abstract" => joe\getAbstract($item, false),
					"category" => $item->categories,
					"views" => joe\getViews($item, false),
					"commentsNum" => number_format($item->commentsNum),
					"agree" => joe\getAgree($item, false),
					"permalink" => $item->permalink,
					"lazyload" => joe\getLazyload(false),
					"type" => "sticky",
					'target' => Helper::options()->Jessay_target,
				);
			}
		}
	}
	$self->widget('Widget_Contents_Sort', 'page=' . $page . '&pageSize=' . $pageSize . '&type=' . $type)->to($item);
	while ($item->next()) {
		$result[] = array(
			"mode" => $item->fields->mode ? $item->fields->mode : 'default',
			"image" => joe\getThumbnails($item),
			"time" => date('Y-m-d', $item->created),
			"created" => date('Y年m月d日', $item->created),
			"title" => $item->title,
			"abstract" => joe\getAbstract($item, false),
			"category" => $item->categories,
			"views" => number_format($item->views),
			"commentsNum" => number_format($item->commentsNum),
			"agree" => number_format($item->agree),
			"permalink" => $item->permalink,
			"lazyload" => joe\getLazyload(false),
			"type" => "normal",
			'target' => Helper::options()->Jessay_target,
		);
	};

	$self->response->throwJson(array("data" => $result));
}

// 百度统计展示
function _getstatistics($self)
{
	$statistics_config = joe\baidu_statistic_config();
	if (is_array($statistics_config)) {
	} else {
		$self->response->setStatus(200);
		$self->response->throwJson(array('access_token' => 'off'));
	}
	if (empty($statistics_config['access_token'])) {
		$self->response->setStatus(200);
		$self->response->throwJson(array('access_token' => 'off'));
	}
	// 获取站点列表
	$baidu_list = function () use ($statistics_config, $self) {
		$url = 'https://openapi.baidu.com/rest/2.0/tongji/config/getSiteList?access_token=' . $statistics_config['access_token'];
		$data = json_decode(file_get_contents($url), true);
		if (isset($data['error_code'])) {
			$self->response->setStatus(404);
			if ($data['error_code'] == 111) {
				$self->response->throwJson(['msg' => '请更新您的access_token']);
			}
			$self->response->throwJson($data);
		}
		return $data['list'];
	};
	// 获取站点详情
	$web_metrics = function ($list, $start_date, $end_date) use ($statistics_config) {
		$access_token = $statistics_config['access_token'];
		$site_id = $list['site_id'];
		$url = "https://openapi.baidu.com/rest/2.0/tongji/report/getData?access_token=$access_token&site_id=$site_id&method=trend/time/a&start_date=$start_date&end_date=$end_date&metrics=pv_count,ip_count&gran=day";
		$data = \network\http\post($url)->toArray();
		$data = $data['result']['sum'][0];
		return $data;
	};
	$domain = $_SERVER['HTTP_HOST'];
	$list = $baidu_list();
	for ($i = 0; $i < count($list); $i++) {
		if ($list[$i]['domain'] == $domain) {
			$list = $list[$i];
			break;
		}
	}
	if ($list['domain'] !== $domain) {
		$data = array(
			'msg' => '没有当前站点'
		);
		$self->response->setStatus(404);
		$self->response->throwJson($data);
	}
	$today = $web_metrics($list, date('Ymd'), date('Ymd'));
	$yesterday = $web_metrics($list, date('Ymd', strtotime("-1 days")), date('Ymd', strtotime("-1 days")));
	$moon = $web_metrics($list, date('Ym') . '01', date('Ymd'));
	$data = array(
		'today' => $today,
		'yesterday' => $yesterday,
		'month' => $moon
	);
	$self->response->setStatus(200);
	$self->response->throwJson($data);
}

/* 增加浏览量 已测试 √ */
function _handleViews($self)
{
	$self->response->setStatus(200);
	$cid = $self->request->cid;
	/* sql注入校验 */
	if (!preg_match('/^\d+$/',  $cid)) {
		return $self->response->throwJson(array("code" => 0, "data" => "非法请求！已屏蔽！"));
	}
	$db = Typecho_Db::get();
	$row = $db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid));
	if (sizeof($row) > 0) {
		$db->query($db->update('table.contents')->rows(array('views' => (int)$row['views'] + 1))->where('cid = ?', $cid));
		$self->response->throwJson(array(
			"code" => 1,
			"data" => array('views' => number_format($db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid))['views']))
		));
	} else {
		$self->response->throwJson(array("code" => 0, "data" => null));
	}
}

/* 点赞和取消点赞 已测试 √ */
function _handleAgree($self)
{
	$self->response->setStatus(200);
	$cid = $self->request->cid;
	$type = $self->request->type;
	/* sql注入校验 */
	if (!preg_match('/^\d+$/',  $cid)) {
		return $self->response->throwJson(array("code" => 0, "data" => "非法请求！已屏蔽！"));
	}
	/* sql注入校验 */
	if (!preg_match('/^[agree|disagree]+$/', $type)) {
		return $self->response->throwJson(array("code" => 0, "data" => "非法请求！已屏蔽！"));
	}
	$db = Typecho_Db::get();
	$row = $db->fetchRow($db->select('agree')->from('table.contents')->where('cid = ?', $cid));
	if (sizeof($row) > 0) {
		if ($type === "agree") {
			$db->query($db->update('table.contents')->rows(array('agree' => (int)$row['agree'] + 1))->where('cid = ?', $cid));
		} else {
			$db->query($db->update('table.contents')->rows(array('agree' => (int)$row['agree'] - 1))->where('cid = ?', $cid));
		}
		$self->response->throwJson(array(
			"code" => 1,
			"data" => array('agree' => number_format($db->fetchRow($db->select('agree')->from('table.contents')->where('cid = ?', $cid))['agree']))
		));
	} else {
		$self->response->throwJson(array("code" => 0, "data" => null));
	}
}

/* 查询是否收录 已测试 √ */
function _getRecord($self)
{
	$self->response->setStatus(200);
	$client = new \network\http\Client;
	$client->param([
		'ie' => 'utf8',
		'wd' => $self->request->site
	]);
	$client->header([
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
		'Accept-Encoding: gzip, deflate',
		'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
		'Connection: keep-alive',
		'Host: www.baidu.com',
		'Referer: https://wappass.baidu.com/',
		'sec-ch-ua: " Not;A Brand";v="99", "Microsoft Edge";v="103", "Chromium";v="103"',
		'sec-ch-ua-mobile: ?0',
		'sec-ch-ua-platform: "Windows"',
		'Sec-Fetch-Dest: document',
		'Sec-Fetch-Mode: navigate',
		'Sec-Fetch-Site: same-site',
		'Sec-Fetch-User: ?1',
		'Upgrade-Insecure-Requests: 1',
		'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.5060.134 Safari/537.36 Edg/103.0.1264.77',
		'Cookie: __yjs_duid=1_ac4d0f87736bc5e2ab5596ce1a7367601643347382579; H_WISE_SIDS=110085_127969_179345_184716_185637_189755_191068_191251_192385_194085_194529_195343_196425_196527_197242_197711_197956_198418_199022_199313_199568_199996_200149_200960_200993_201108_201192_201545_201707_202059_202759_202910_203309_203360_203519_203605_203886_204031_204132_204265_204322_204405_204432_204675_204725_204824_204859_204919_204940_205009_205087_205094_205218_205380_205386_205412_205485_205656_205690_205710_205831_205847_205919_206098_206283_206476_206767_206927_207005_207124_207136_207212_207234_207363_207497_207506_8000076_8000128_8000140_8000150_8000159_8000163_8000167_8000177_8000179_8000186; BD_UPN=12314753; PSTM=1656921064; BIDUPSID=1C10D9F853DBCC6E9738B268FCC46875; BAIDUID=40E6CCC7EEB3D860EB05C626C3F2C44B:FG=1; H_WISE_SIDS_BFESS=110085_127969_179345_184716_185637_189755_191068_191251_192385_194085_194529_195343_196425_196527_197242_197711_197956_198418_199022_199313_199568_199996_200149_200960_200993_201108_201192_201545_201707_202059_202759_202910_203309_203360_203519_203605_203886_204031_204132_204265_204322_204405_204432_204675_204725_204824_204859_204919_204940_205009_205087_205094_205218_205380_205386_205412_205485_205656_205690_205710_205831_205847_205919_206098_206283_206476_206767_206927_207005_207124_207136_207212_207234_207363_207497_207506_8000076_8000128_8000140_8000150_8000159_8000163_8000167_8000177_8000179_8000186; BDORZ=B490B5EBF6F3CD402E515D22BCDA1598; BA_HECTOR=81al8g05a48lal01052l1cdj1heel6a16; ZFY=UN3DgzqvtqoeRQZLRr7OUad79UfJKR3Npye2ytuzKYQ:C; delPer=0; PSINO=2; BD_HOME=1; H_PS_PSSID=36832_36559_36753_36726_36413_36955_36167_36918_36570_36804_36965_36740_26350_22160'
	]);
	$output = $client->get('https://www.baidu.com/s');
	$res = str_replace([' ', "\n", "\r"], '', $output);
	if ((strpos($res, "抱歉，没有找到与")) || (strpos($res, "找到相关结果约0个")) || (strpos($res, "没有找到该URL")) || (strpos($res, "抱歉没有找到"))) {
		$self->response->throwJson(array("data" => "未收录"));
	}
	if ((strpos($res, '页面不存在_百度搜索')) || (strpos($res, '百度安全验证'))) {
		$self->response->throwJson(array("data" => "检测失败"));
	}
	$self->response->throwJson(array("data" => "已收录"));
}

/* 主动推送到百度收录 已测试 √ */
function _pushRecord($self)
{
	$self->response->setStatus(200);
	$token = Helper::options()->JBaiduToken;
	$domain = $self->request->domain;
	$url = $self->request->url;
	$urls = explode(",", $url);
	$api = "http://data.zz.baidu.com/urls?site={$domain}&token={$token}";
	$ch = curl_init();
	$options =  array(
		CURLOPT_URL => $api,
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => implode("\n", $urls),
		CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
	);
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	curl_close($ch);
	$self->response->throwJson(array(
		'domain' => $domain,
		'url' => $url,
		'data' => json_decode($result, TRUE)
	));
}

// 主动推送到必应收录
function _pushBing($self)
{
	$self->response->setStatus(200);
	$token = Helper::options()->JBingToken;
	if (empty($token)) {
		exit;
	}
	$domain = $self->request->domain;  //网站域名
	$url = $self->request->url;
	$urls = explode(",", $url);  //要推送的url
	$api = "https://www.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=$token";
	$data = array(
		'siteUrl' => $domain,
		'urlList' => $urls
	);
	$ch = curl_init();
	$options =  array(
		CURLOPT_URL => $api,
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => json_encode($data),
		CURLOPT_HTTPHEADER => array('Content-Type: application/json; charset=utf-8', 'Host: ssl.bing.com'),
	);
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	curl_close($ch);
	$self->response->throwJson(array(
		'domain' => $domain,
		'url' => $url,
		'data' => json_decode($result, TRUE)
	));
}

/* 获取壁纸分类 已测试 √ */
function _getWallpaperType($self)
{
	$self->response->setStatus(200);
	$json = \network\http\get("http://cdn.apc.360.cn/index.php?c=WallPaper&a=getAllCategoriesV2&from=360chrome");
	$res = json_decode($json, TRUE);
	if ($res['errno'] == 0) {
		$self->response->throwJson([
			"code" => 1,
			"data" => $res['data']
		]);
	} else {
		$self->response->throwJson([
			"code" => 0,
			"data" => null
		]);
	}
}

/* 获取壁纸列表 已测试 √ */
function _getWallpaperList($self)
{
	$self->response->setStatus(200);

	$cid = $self->request->cid;
	$start = $self->request->start;
	$count = $self->request->count;
	$json = \network\http\get("http://wallpaper.apc.360.cn/index.php?c=WallPaper&a=getAppsByCategory&cid={$cid}&start={$start}&count={$count}&from=360chrome");
	$res = json_decode($json, TRUE);
	if ($res['errno'] == 0) {
		$self->response->throwJson([
			"code" => 1,
			"data" => $res['data'],
			"total" => $res['total']
		]);
	} else {
		$self->response->throwJson([
			"code" => 0,
			"data" => null
		]);
	}
}

/* 抓取苹果CMS视频分类 已测试 √ */
function _getMaccmsList($self)
{
	$self->response->setStatus(200);

	$cms_api = Helper::options()->JMaccmsAPI;
	$ac = $self->request->ac ? $self->request->ac : '';
	$ids = $self->request->ids ? $self->request->ids : '';
	$t = $self->request->t ? $self->request->t : '';
	$pg = $self->request->pg ? $self->request->pg : '';
	$wd = $self->request->wd ? $self->request->wd : '';
	if ($cms_api) {
		$json = \network\http\get("{$cms_api}?ac={$ac}&ids={$ids}&t={$t}&pg={$pg}&wd={$wd}");
		$res = json_decode($json, TRUE);
		if ($res['code'] === 1) {
			$self->response->throwJson([
				"code" => 1,
				"data" => $res,
			]);
		} else {
			$self->response->throwJson([
				"code" => 0,
				"data" => "抓取失败！请联系作者！"
			]);
		}
	} else {
		$self->response->throwJson([
			"code" => 0,
			"data" => "后台苹果CMS API未填写！"
		]);
	}
}

/* 获取虎牙视频列表 已测试 √ */
function _getHuyaList($self)
{
	$self->response->setStatus(200);

	$gameId = $self->request->gameId;
	$page = $self->request->page;
	$json = \network\http\get("https://www.huya.com/cache.php?m=LiveList&do=getLiveListByPage&gameId={$gameId}&tagAll=0&page={$page}");
	$res = json_decode($json, TRUE);
	if ($res['status'] === 200) {
		$self->response->throwJson([
			"code" => 1,
			"data" => $res['data'],
		]);
	} else {
		$self->response->throwJson([
			"code" => 0,
			"data" => "抓取失败！请联系作者！"
		]);
	}
}

/* 获取服务器状态 */
function _getServerStatus($self)
{
	$self->response->setStatus(200);

	$api_panel = Helper::options()->JBTPanel;
	$api_sk = Helper::options()->JBTKey;
	if (!$api_panel) return $self->response->throwJson([
		"code" => 0,
		"data" => "宝塔面板地址未填写！"
	]);
	if (!$api_sk) return $self->response->throwJson([
		"code" => 0,
		"data" => "宝塔接口密钥未填写！"
	]);
	$request_time = time();
	$request_token = md5($request_time . '' . md5($api_sk));
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_panel . '/system?action=GetNetWork');
	curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3000);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,  array("request_time" => $request_time, "request_token" => $request_token));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$response  = json_decode(curl_exec($ch), true);
	curl_close($ch);
	$self->response->throwJson(array(
		/* 状态 */
		"status" => $response ? true : false,
		/* 信息提示 */
		"message" => $response['msg'],
		/* 上行流量KB */
		"up" => $response["up"] ? $response["up"] : 0,
		/* 下行流量KB */
		"down" => $response["down"] ? $response["down"] : 0,
		/* 总发送（字节数） */
		"upTotal" => $response["upTotal"] ? $response["upTotal"] : 0,
		/* 总接收（字节数） */
		"downTotal" => $response["downTotal"] ? $response["downTotal"] : 0,
		/* 内存占用 */
		"memory" => $response["mem"] ? $response["mem"] : ["memBuffers" => 0, "memCached" => 0, "memFree" => 0, "memRealUsed" => 0, "memTotal" => 0],
		/* CPU */
		"cpu" => $response["cpu"] ? $response["cpu"] : [0, 0, [0], 0, 0, 0],
		/* 系统负载 */
		"load" => $response["load"] ? $response["load"] : ["fifteen" => 0, "five" => 0, "limit" => 0, "max" => 0, "one" => 0, "safe" => 0],
	));
}

/* 获取最近评论 */
function _getCommentLately($self)
{
	$self->response->setStatus(200);

	$time = time();
	$num = 7;
	$categories = [];
	$series = [];
	$db = Typecho_Db::get();
	$prefix = $db->getPrefix();
	for ($i = ($num - 1); $i >= 0; $i--) {
		$date = date("Y/m/d", $time - ($i * 24 * 60 * 60));
		$sql = "SELECT coid FROM `{$prefix}comments` WHERE FROM_UNIXTIME(created, '%Y/%m/%d') = '{$date}' limit 100";
		$count = count($db->fetchAll($sql));
		$categories[] = $date;
		$series[] = $count;
	}
	$self->response->throwJson([
		"categories" => $categories,
		"series" => $series,
	]);
}

/* 获取文章归档 */
function _getArticleFiling($self)
{
	$self->response->setStatus(200);

	$page = $self->request->page;
	$pageSize = 8;
	if (!preg_match('/^\d+$/', $page)) return $self->response->throwJson(array("data" => "非法请求！已屏蔽！"));
	if ($page == 0) $page = 1;
	$offset = $pageSize * ($page - 1);
	$time = time();
	$db = Typecho_Db::get();
	$prefix = $db->getPrefix();
	$result = [];
	$sql_version = $db->fetchAll('select VERSION()')[0]['VERSION()'];
	if ($sql_version >= 8) {
		$sql = "SELECT FROM_UNIXTIME(created, '%Y 年 %m 月') as date FROM `{$prefix}contents` WHERE created < {$time} AND (password is NULL or password = '') AND status = 'publish' AND type = 'post' GROUP BY FROM_UNIXTIME(created, '%Y 年 %m 月') LIMIT {$pageSize} OFFSET {$offset}";
	} else {
		$sql = "SELECT FROM_UNIXTIME(created, '%Y 年 %m 月') as date FROM `{$prefix}contents` WHERE created < {$time} AND (password is NULL or password = '') AND status = 'publish' AND type = 'post' GROUP BY FROM_UNIXTIME(created, '%Y 年 %m 月') DESC LIMIT {$pageSize} OFFSET {$offset}";
	}
	$temp = $db->fetchAll($sql);
	$options = Typecho_Widget::widget('Widget_Options');
	foreach ($temp as $item) {
		$date = $item['date'];
		$list = [];
		$sql = "SELECT * FROM `{$prefix}contents` WHERE created < {$time} AND (password is NULL or password = '') AND status = 'publish' AND type = 'post' AND FROM_UNIXTIME(created, '%Y 年 %m 月') = '{$date}' ORDER BY created DESC LIMIT 100";
		$_list = $db->fetchAll($sql);
		foreach ($_list as $_item) {
			$type = $_item['type'];
			$_item['categories'] = $db->fetchAll($db->select()->from('table.metas')
				->join('table.relationships', 'table.relationships.mid = table.metas.mid')
				->where('table.relationships.cid = ?', $_item['cid'])
				->where('table.metas.type = ?', 'category')
				->order('table.metas.order', Typecho_Db::SORT_ASC));
			$_item['category'] = urlencode(current(Typecho_Common::arrayFlatten($_item['categories'], 'slug')));
			$_item['slug'] = urlencode($_item['slug']);
			$_item['date'] = new Typecho_Date($_item['created']);
			$_item['year'] = $_item['date']->year;
			$_item['month'] = $_item['date']->month;
			$_item['day'] = $_item['date']->day;
			$routeExists = (NULL != Typecho_Router::get($type));
			$_item['pathinfo'] = $routeExists ? Typecho_Router::url($type, $_item) : '#';
			$_item['permalink'] = Typecho_Common::url($_item['pathinfo'], $options->index);
			$list[] = array(
				"title" => date('m/d', $_item['created']) . '：' . $_item['title'],
				"permalink" => $_item['permalink'],
			);
		}
		$result[] = array("date" => $date, "list" => $list);
	}
	$self->response->throwJson($result);
}

// 提交友情链接
function _friendSubmit($self)
{
	$self->response->setStatus(200);
	$title = $self->request->title;
	$description = $self->request->description;
	$link = $self->request->link;
	$logo = $self->request->logo;
	$qq = $self->request->qq;
	if (empty($title) || empty($link) || empty($qq)) {
		$self->response->throwJson([
			'code' => 0,
			'msg' => '必填项不能为空'
		]);
	}
	if (empty($logo)) {
		$logo = 'http://q4.qlogo.cn/headimg_dl?dst_uin=' . $qq . '&spec=640';
	}
	$EmailTitle = '友链申请';
	$subtitle = $title . '向您提交了友链申请：';
	$content = "$title || $link || $logo || $description<br><br>对方QQ号：$qq";
	$SendEmail = joe\send_email($EmailTitle, $subtitle, $content);
	if ($SendEmail == 'success') {
		$self->response->throwJson([
			'code' => 1,
			'msg' => '提交成功，管理员会在24小时内进行审核，请耐心等待'
		]);
	}
	if (!empty($SendEmail)) {
		$self->response->throwJson([
			'code' => 0,
			'msg' => '提交失败，错误原因：' . $SendEmail
		]);
	}
	if ($SendEmail == false) {
		$self->response->throwJson([
			'code' => 0,
			'msg' => '提交失败，请联系本站点管理员进行处理'
		]);
	}
}