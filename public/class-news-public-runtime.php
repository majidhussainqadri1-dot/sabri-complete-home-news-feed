<?php
/**
 * Public Editorial News rendering runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Owns public rendering context and escaped template boundaries. */
final class NewsPublicRuntime {
	private static $context = array();

	public static function register() {
		if ( function_exists( 'add_filter' ) ) { add_filter( 'body_class', array( __CLASS__, 'body_class' ) ); }
	}
	public static function set_context( array $context ) { self::$context=$context; }
	public static function context() { return self::$context; }

	/** Render the current landing/archive body. */
	public static function render_archive() {
		$context=self::$context; Assets::enqueue_news();
		if ( 'landing' === (isset($context['route'])?$context['route']:'') ) {
			$result=isset($context['result'])&&is_array($context['result'])?$context['result']:array();
			$data=isset($result['data'])&&is_array($result['data'])?$result['data']:array();
			return self::template('news-home',array(
				'title'=>isset($context['title'])?$context['title']:__('News','sabri-complete-home-news-feed'),
				'components'=>isset($data['components'])?$data['components']:array(),
				'filter_form'=>self::filter_form(array()),
				'empty_html'=>self::template('news-empty-state',array()),
			));
		}
		$result=isset($context['result'])&&is_array($context['result'])?$context['result']:array();
		$data=isset($result['data'])&&is_array($result['data'])?$result['data']:array();
		return self::template('news-archive',array(
			'title'=>isset($context['title'])?$context['title']:__('News','sabri-complete-home-news-feed'),
			'description'=>isset($context['description'])?$context['description']:'',
			'items'=>isset($data['items'])?$data['items']:array(),
			'filters'=>isset($data['filters'])?$data['filters']:array(),
			'pagination'=>self::pagination($data),'filter_form'=>self::filter_form(isset($data['filters'])?$data['filters']:array()),
			'empty_html'=>self::template('news-empty-state',array()),
		));
	}

	public static function render_single() {
		$context=self::$context; $article=isset($context['article'])&&is_array($context['article'])?$context['article']:array(); Assets::enqueue_news();
		if('retraction'===(isset($article['projection'])?$article['projection']:'')){return self::template('news-retraction-notice',array('article'=>$article));}
		return self::template('news-single',array(
			'article'=>$article,'related'=>!empty($article['id'])?NewsQueryService::related($article['id'],4):array(),
			'interactions'=>class_exists(__NAMESPACE__.'\\SocialRuntime')?SocialRuntime::render_news_action_bar($article):'',
		));
	}

	public static function render_card( array $item ) {
		if('editorial_news'!==(isset($item['item_type'])?$item['item_type']:'')||empty($item['global_key'])){return '';}
		Assets::enqueue_news();
		return self::template('news-card',array('item'=>$item));
	}

	public static function template( $template, array $vars=array() ) {
		$file=SABRI_HNF_PATH.'templates/'.sanitize_key($template).'.php'; if(!is_readable($file)){return '';}
		ob_start(); extract($vars,EXTR_SKIP); include $file; return (string)ob_get_clean();
	}
	public static function body_class( $classes ) {
		$classes=is_array($classes)?$classes:array(); if(!empty(self::$context['route'])){$classes[]='sabri-news-route';$classes[]='sabri-news-route--'.sanitize_html_class(self::$context['route']);} return array_values(array_unique($classes));
	}

	private static function pagination( array $data ) {
		$page=isset($data['page'])?(int)$data['page']:1; $max=isset($data['max_pages'])?(int)$data['max_pages']:0; if($max<=1){return '';}
		if(function_exists('paginate_links')){ $links=paginate_links(array('base'=>self::page_url('%#%'),'format'=>'','current'=>$page,'total'=>$max,'type'=>'list','prev_text'=>__('Previous','sabri-complete-home-news-feed'),'next_text'=>__('Next','sabri-complete-home-news-feed'))); return is_string($links)?$links:''; }
		return '';
	}

	/** Complete accessible server-rendered filter form. */
	private static function filter_form( array $filters ) {
		$sections=NewsQueryService::public_terms('sabri_news_section'); $types=NewsQueryService::public_terms('sabri_news_type');
		$topics=NewsQueryService::public_terms('sabri_news_topic'); $countries=NewsQueryService::public_terms('sabri_news_country'); $regions=NewsQueryService::public_terms('sabri_news_region');
		ob_start(); ?>
		<form class="sabri-news-filter" method="get" action="<?php echo esc_url(isset(self::$context['canonical_base'])?self::$context['canonical_base']:(function_exists('home_url')?home_url('/news/'):'/news/')); ?>">
			<div class="sabri-news-filter__field"><label for="sabri-news-keyword"><?php echo esc_html__('Search News','sabri-complete-home-news-feed'); ?></label><input id="sabri-news-keyword" type="search" name="q" maxlength="100" value="<?php echo esc_attr(isset($filters['keyword'])?$filters['keyword']:''); ?>" /></div>
			<?php self::select_field('section',__('Section','sabri-complete-home-news-feed'),$sections,isset($filters['section'])?$filters['section']:''); ?>
			<?php self::select_field('type',__('Article type','sabri-complete-home-news-feed'),$types,isset($filters['type'])?$filters['type']:''); ?>
			<?php self::select_field('topic',__('Topic','sabri-complete-home-news-feed'),$topics,isset($filters['topic'])?$filters['topic']:''); ?>
			<?php self::select_field('country',__('Country','sabri-complete-home-news-feed'),$countries,isset($filters['country'])?$filters['country']:''); ?>
			<?php self::select_field('region',__('Region','sabri-complete-home-news-feed'),$regions,isset($filters['region'])?$filters['region']:''); ?>
			<div class="sabri-news-filter__field"><label for="sabri-news-date-from"><?php echo esc_html__('Published from','sabri-complete-home-news-feed'); ?></label><input id="sabri-news-date-from" type="date" name="date_from" value="<?php echo esc_attr(isset($filters['date_from'])?$filters['date_from']:''); ?>" /></div>
			<div class="sabri-news-filter__field"><label for="sabri-news-date-to"><?php echo esc_html__('Published to','sabri-complete-home-news-feed'); ?></label><input id="sabri-news-date-to" type="date" name="date_to" value="<?php echo esc_attr(isset($filters['date_to'])?$filters['date_to']:''); ?>" /></div>
			<div class="sabri-news-filter__field"><label for="sabri-news-author"><?php echo esc_html__('Approved author ID','sabri-complete-home-news-feed'); ?></label><input id="sabri-news-author" type="number" min="1" step="1" name="author" value="<?php echo esc_attr(!empty($filters['author'])?(int)$filters['author']:''); ?>" /></div>
			<div class="sabri-news-filter__field"><label for="sabri-news-institution"><?php echo esc_html__('Institution slug','sabri-complete-home-news-feed'); ?></label><input id="sabri-news-institution" type="text" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="120" name="institution" value="<?php echo esc_attr(isset($filters['institution'])?$filters['institution']:''); ?>" /></div>
			<fieldset class="sabri-news-filter__checks"><legend><?php echo esc_html__('Status filters','sabri-complete-home-news-feed'); ?></legend>
				<?php self::checkbox('research',__('Research News only','sabri-complete-home-news-feed'),!empty($filters['research'])); ?>
				<?php self::checkbox('corrected',__('Corrected articles only','sabri-complete-home-news-feed'),!empty($filters['corrected'])); ?>
				<?php self::checkbox('retracted',__('Retraction notices only','sabri-complete-home-news-feed'),!empty($filters['retracted'])); ?>
			</fieldset>
			<div class="sabri-news-filter__actions"><button type="submit"><?php echo esc_html__('Apply filters','sabri-complete-home-news-feed'); ?></button><a class="sabri-news-filter__clear" href="<?php echo esc_url(self::page_url(1,true)); ?>"><?php echo esc_html__('Clear','sabri-complete-home-news-feed'); ?></a></div>
		</form><?php return (string)ob_get_clean();
	}

	private static function select_field($name,$label,array $options,$selected){
		$id='sabri-news-'.sanitize_html_class($name); echo '<div class="sabri-news-filter__field"><label for="'.esc_attr($id).'">'.esc_html($label).'</label><select id="'.esc_attr($id).'" name="'.esc_attr($name).'"><option value="">'.esc_html__('All','sabri-complete-home-news-feed').'</option>';
		foreach($options as $option){$slug=isset($option['slug'])?$option['slug']:'';$title=isset($option['name'])?$option['name']:'';if(''===$slug||''===$title){continue;}echo '<option value="'.esc_attr($slug).'"'.($selected===$slug?' selected="selected"':'').'>'.esc_html($title).'</option>';}
		echo '</select></div>';
	}
	private static function checkbox($name,$label,$checked){$id='sabri-news-'.sanitize_html_class($name);echo '<label class="sabri-news-filter__check" for="'.esc_attr($id).'"><input id="'.esc_attr($id).'" type="checkbox" name="'.esc_attr($name).'" value="1"'.($checked?' checked="checked"':'').' /> '.esc_html($label).'</label>';}

	private static function page_url( $page, $clear=false ) {
		$base=isset(self::$context['canonical_base'])?self::$context['canonical_base']:(function_exists('home_url')?home_url('/news/'):'/news/'); if($clear){return $base;}
		$args=array();$filters=isset(self::$context['result']['data']['filters'])?self::$context['result']['data']['filters']:array();
		foreach(array('keyword','section','topic','country','region','type','date_from','date_to','author','institution','research','corrected','retracted') as $key){if(!empty($filters[$key])){$args['keyword'===$key?'q':$key]=$filters[$key];}}
		$args['page']=$page; return function_exists('add_query_arg')?add_query_arg($args,$base):$base;
	}
}
