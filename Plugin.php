<?php

/**
 * <a href='https://blog.irow.top/archives/387.html' title='mainpage' target='_blank'>百度主动提交工具</a>
 *
 * @package ActiveSubmit
 * @author 承影
 * @version 0.2
 * @link https://blog.irow.top/
 */

class ActiveSubmit_Plugin implements Typecho_Plugin_Interface
{
    /* 激活插件方法 */
    public static function activate()
    {
      Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('ActiveSubmit_Plugin', 'render');
    }

    /* 禁用插件方法 */
    public static function deactivate(){}

    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
      $api = new Typecho_Widget_Helper_Form_Element_Text(
        'api', NULL, '',
        _t('百度推送接口：'),
        _t('如果不知道自己的百度推送接口，请到<a href=\'https://ziyuan.baidu.com/linksubmit/\' title=\'百度资源搜索平台\' target=\'_blank\'>百度搜索资源平台</a>获取')
      );
      $form->addInput($api);
    }

    /* 个人用户的配置方法 */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /* 插件实现方法 */
    public static function render($contents, $class){
      $options = Helper::options();
      //确认是否发布
      if ('publish' != $contents['visibility'] || $contents['created'] > time()) {
          return;
      }
      //判断是否配置相关信息
      if (is_null($options->plugin('ActiveSubmit')->api)) {
          throw new Typecho_Plugin_Exception(_t('参数未正确配置，自动提交失败'));
      }else{
          $api = $options->plugin('ActiveSubmit')->api;
      }
      //一天之内的文章推送
      if((int)$article['created']+86400 < (int)$article['modified'] ){//之前判断忽略了自动保存草稿的问题
          return;
      }
      //构造链接
      $post = array('cid' => $class->cid);
      $options = Helper::options();
      $db = Typecho_Db::get();

      $routeExists = (NULL != Typecho_Router::get('post'));
      if(!is_null($routeExists)){
          $post['categories'] = $db->fetchAll($db->select()->from('table.metas')
                  ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                  ->where('table.relationships.cid = ?', $post['cid'])
                  ->where('table.metas.type = ?', 'category')
                  ->order('table.metas.order', Typecho_Db::SORT_ASC));

          $post['category'] = urlencode(current(Typecho_Common::arrayFlatten($post['categories'], 'slug')));
          $post['directory']=$post['category'];
          foreach ($post['categories'] as $category) {
              if(0!=$category['parent']){
                  $parent = $db->fetchRow($db->select()->from('table.metas')
                      ->where('table.metas.mid = ?',$category['parent']));
                  $post['directory'] = urlencode($parent['slug']).'/'.urlencode($category['slug']);
                  break;
              }
          }
          $post['slug'] = urlencode($post['slug']);
          $post['date'] = new Typecho_Date($post['created']);
          $post['year'] = $post['date']->year;
          $post['month'] = $post['date']->month;
          $post['day'] = $post['date']->day;
      }

      $post['pathinfo'] = $routeExists ? Typecho_Router::url('post', $post) : '#';
      $url = Typecho_Common::url($post['pathinfo'], $options->index);

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

      /*错误日志
      $route = __TYPECHO_ROOT_DIR__.'/usr/plugins/ActiveSubmit/'
      $file = $route.'error.log';
      if($result){
        $result = json_decode($result);
        if(1!=$result['success']){
          file_put_contents($file, json_encode($result)."\n", FILE_APPEND);
        }
      }
      else{
        file_put_contents($file, json_encode($result)."\n", FILE_APPEND);
      }
      */
    }

}
