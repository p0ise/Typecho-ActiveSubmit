<?php

/**
 * <a href='https://blog.irow.top/archives/562.html' title='mainpage' target='_blank'>百度主动提交工具</a>
 *
 * @package ActiveSubmit
 * @author 承影
 * @version 1.0
 * @link https://blog.irow.top/
 */

class ActiveSubmit_Plugin implements Typecho_Plugin_Interface
{
    /* 激活插件方法 */
    public static function activate()
    {
      Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('ActiveSubmit_Plugin', 'submit');
      Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('ActiveSubmit_Plugin', 'submit');
      Typecho_Plugin::factory('Widget_Contents_Post_Edit')->delete = array('ActiveSubmit_Plugin', 'del');
      Typecho_Plugin::factory('Widget_Contents_Page_Edit')->delete = array('ActiveSubmit_Plugin', 'del');
    }

    /* 禁用插件方法 */
    public static function deactivate(){}

    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
      $token = new Typecho_Widget_Helper_Form_Element_Text(
        'token', NULL, '',
        _t('百度推送接口token：'),
        _t('如果不知道自己的百度推送接口token，请到<a href=\'https://ziyuan.baidu.com/linksubmit/\' title=\'百度资源搜索平台\' target=\'_blank\'>百度搜索资源平台</a>获取')
      );
      $form->addInput($token);
      $error = new Typecho_Widget_Helper_Form_Element_Radio('error',
          ['0' => _t('否'), '1' => _t('是')],
          '0', _t('是否开启错误日志'), _t('开启后在插件目录下会生成error.log记录错误(需要插件目录可写权限)'));
      $form->addInput($error);
    }

    /* 个人用户的配置方法 */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /* 插件实现方法 */
    public static function render(){}

    public static function submit($contents, $edit)
    {
      $url = $edit->permalink;

      if (is_null(Helper::options()->plugin('ActiveSubmit')->token)) {
          throw new Typecho_Plugin_Exception(_t('参数未正确配置，自动提交失败'));
      }else{
          $token = Helper::options()->plugin('ActiveSubmit')->token;
      }

      //确认是否发布
      if ('publish' != $contents['visibility'] || $contents['created'] > time()) {
          return;
      }
      //一天之内的文章不再次推送
      if((int)$article['created']+86400 > (int)$article['modified']){
          return;
      }

	    $baseurl = str_replace("/index.php", "", Helper::options()->index);

      $method = 'urls';

      //更新文章
      if((int)$article['created']!=(int)$article['modified'] ){
        $method = 'update';
      }

      $api = "http://data.zz.baidu.com/update?site={$baseurl}&token={$token}";

      //主动推送
      $ch = curl_init();
      $options =  array(
          CURLOPT_URL => $api,
          CURLOPT_POST => true,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_POSTFIELDS => $url,
          CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
      );
      curl_setopt_array($ch, $options);
      $result = curl_exec($ch);
      curl_close($ch);

      /*错误日志*/
      if(Helper::options()->plugin('ActiveSubmit')->error){
        $route = __TYPECHO_ROOT_DIR__.'/usr/plugins/ActiveSubmit/';
        $file = $route.'error.log';
        if($result){
          $result = json_decode($result, $assoc=true);
          if(1!=$result['success']){
            file_put_contents($file, date('Y-m-d-H:i:s')."\t{$method}\t".json_encode($result)."\n", FILE_APPEND);
          }
        }
        else{
          file_put_contents($file, date('Y-m-d-H:i:s')."\t{$method}\t".json_encode($result)."\n", FILE_APPEND);
        }
      }

    }


    public static function del($cid, $edit){

      $url = self::get_url($cid);

      //判断是否配置相关信息
      if (is_null(Helper::options()->plugin('ActiveSubmit')->token)) {
          throw new Typecho_Plugin_Exception(_t('参数未正确配置，自动提交失败'));
      }else{
          $token = Helper::options()->plugin('ActiveSubmit')->token;
      }

      $baseurl = str_replace("/index.php", "", Helper::options()->index);

      $api = "http://data.zz.baidu.com/del?site={$baseurl}&token={$token}";

      //主动推送
      $ch = curl_init();
      $options =  array(
          CURLOPT_URL => $api,
          CURLOPT_POST => true,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_POSTFIELDS => $url,
          CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
      );
      curl_setopt_array($ch, $options);
      $result = curl_exec($ch);
      curl_close($ch);

      /*错误日志*/
      if(Helper::options()->plugin('ActiveSubmit')->error){
        $route = __TYPECHO_ROOT_DIR__.'/usr/plugins/ActiveSubmit/';
        $file = $route.'error.log';
        if($result){
          $result = json_decode($result, $assoc=true);
          if(1!=$result['success']){
            file_put_contents($file, date('Y-m-d-H:i:s')."\tdelete\t".json_encode($result)."\n", FILE_APPEND);
          }
        }
        else{
          file_put_contents($file, date('Y-m-d-H:i:s')."\tdelete\t".json_encode($result)."\n", FILE_APPEND);
        }
      }
    }

    public static function get_url($cid)
    {
      $db = Typecho_Db::get();
      $value = array();
      /** 取出所有分类 */
      $value['cid'] = $cid;
      $value['type'] = $db->fetchRow($db->select('type')->from('table.contents')->where('table.contents.cid = ?', $value['cid']))['type'];
      $value['categories'] = $db->fetchAll($db
          ->select()->from('table.metas')
          ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
          ->where('table.relationships.cid = ?', $value['cid'])
          ->where('table.metas.type = ?', 'category')
          ->order('table.metas.order', Typecho_Db::SORT_ASC));
      $value['category'] = NULL;
      $value['directory'] = array();

      /** 取出第一个分类作为slug条件 */
      if (!empty($value['categories'])) {
          $value['category'] = $value['categories'][0]['slug'];

          $value['directory'] = Typecho_Widget::widget('Widget_Metas_Category_List')->getAllParentsSlug($value['categories'][0]['mid']);
          $value['directory'][] = $value['category'];
      }


      /** 生成访问权限 */
      $value['hidden'] = false;

      /** 获取路由类型并判断此类型在路由表中是否存在 */
      $type = $value['type'];
      $routeExists = (NULL != Typecho_Router::get($type));

      $value['slug'] = urlencode($value['slug']);
      $value['category'] = urlencode($value['category']);
      $value['directory'] = implode('/', array_map('urlencode', $value['directory']));

      /** 生成静态路径 */
      $value['pathinfo'] = $routeExists ? Typecho_Router::url($type, $value) : '#';

      /** 生成静态链接 */
      $value['permalink'] = Typecho_Common::url($value['pathinfo'], Helper::options()->index);

      return $value['permalink'];
    }

}
