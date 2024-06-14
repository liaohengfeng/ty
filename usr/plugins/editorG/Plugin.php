<?php
/**
 * 针对默认编辑器的辅助插件,通过css优化手机端默认编辑器样式，同时增加个代码按钮
 *
 * @package editorG
 * @author Jrotty
 * @version 0.2
 * @link http://qqdie.com
 */
class editorG_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 插件版本号
     * @var string
     */
    const _VERSION = '0.2';
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static Function activate()
    {
		Typecho_Plugin::factory('admin/write-post.php')->bottom = array('editorG_Plugin', 'button');
		Typecho_Plugin::factory('admin/write-page.php')->bottom = array('editorG_Plugin', 'button');
	}


public static function button(){
		?><style>.wmd-button-row {
    height: auto;
}</style>
		<script> 
          $(document).ready(function(){
          	$('#wmd-button-row').append('<li class="wmd-button" id="wmd-jrotty-button" title="代码 - ALT+C"><span style="background: none;font-size: large;text-align: center;color: #999999;font-family: serif;">C</span></li>');
				if($('#wmd-button-row').length !== 0){
					$('#wmd-jrotty-button').click(function(){
						var rs = "```\nyour code\n```\n";
						zeze(rs);
					})
				}


				function zeze(tag) {
					var myField;
					if (document.getElementById('text') && document.getElementById('text').type == 'textarea') {
						myField = document.getElementById('text');
					} else {
						return false;
					}
					if (document.selection) {
						myField.focus();
						sel = document.selection.createRange();
						sel.text = tag;
						myField.focus();
					}
					else if (myField.selectionStart || myField.selectionStart == '0') {
						var startPos = myField.selectionStart;
						var endPos = myField.selectionEnd;
						var cursorPos = startPos;
						myField.value = myField.value.substring(0, startPos)
						+ tag
						+ myField.value.substring(endPos, myField.value.length);
						cursorPos += tag.length;
						myField.focus();
						myField.selectionStart = cursorPos;
						myField.selectionEnd = cursorPos;
					} else {
						myField.value += tag;
						myField.focus();
					}
				}

				$('body').on('keydown',function(a){
					if( a.altKey && a.keyCode == "67"){
						$('#wmd-jrotty-button').click();
					}
				});


			});
</script>
<?php
}

	
    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){}

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}



}
