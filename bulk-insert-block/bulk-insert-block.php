<?php
/**
 * Plugin Name: Bulk Insert Block
 * Description: Add a selected Gutenberg block to multiple posts, with bulk-test/apply, dynamic attributes, and debug SQL output
 * Version: 1.0
 * Author: Jeffrey Powers with help from OpenAI's ChatGPT.
 * Donate: https://paypal.me/jeffpowers or https://venmo.com/geekazine. You can also become a Patreon - https://patreon.com/geekazine
 - NOTE: This is freeware - BACKUP YOUR DATABASE - There is no support, so use at your own discresion. 
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Bulk_Insert_Block_Plugin {
    const PER_PAGE = 25;

    public function __construct() {
        add_action( 'admin_menu',           [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts',[ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_bib_test_block',   [ $this, 'ajax_test_block' ] );
        add_action( 'wp_ajax_bib_insert_block', [ $this, 'ajax_insert_block' ] );
    }

    public function add_menu() {
        add_submenu_page(
            'edit.php',
            'Bulk Insert Block',
            'Bulk Insert Block',
            'edit_posts',
            'bulk-insert-block',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook === 'posts_page_bulk-insert-block' ) {
            wp_enqueue_script( 'jquery' );
        }
    }

    private function get_block_list() {
        return array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
    }

    public function render_page() {
        $blocks   = $this->get_block_list();
        $settings = [
            'blocks'   => $blocks,
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bib_nonce' ),
        ];
        ?>
        <div class="wrap" style="overflow:auto;">
            <h1>Bulk Insert Block</h1>

            <!-- Sidebar box -->
            <div id="bib-sidebar" style="float:right;width:25%;background:#fff;border:1px solid #ccc;padding:15px;margin:0 0 20px 20px;">
                <h2>Plugin Info</h2>
                <p>I made this to insert simple blocks into multiple posts. Blocks may or may not insert, depending on if they need additional information. That is why I set up the test before you run area. There is no support on this plugin, but if you would like to help contribute, <a href="https://geekazine.com/contact">contact me </a></p>
                <h3>Support</h3>
                <p><a href="https://paypal.me/jeffpowers" target="_blank">PayPal</a> - 
					<a href="https://venmo.com/geekazine" target="_blank">Venmo</a> - 
                   <a href="https://patreon.com/geekazine" target="_blank">Patreon</a>
				</p>
            </div>

            <!-- Results box -->
            <div id="bib-results" style="background:#e6f7ff;border:1px solid #b3d8ff;padding:15px;border-radius:4px;margin-bottom:20px;text-align:left;">
                <h2 style="margin:0 0 10px 0;padding:0;">Results</h2>
                <p>Nothing run yet</p>
            </div>

            <form method="get" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="bulk-insert-block">
                <label>Days old: <input type="number" name="days_old" value="<?php echo esc_attr( $_GET['days_old'] ?? '' ); ?>" style="width:80px;"></label>
                <?php wp_dropdown_categories([ 'show_option_all'=>'All Categories','name'=>'category','selected'=>$_GET['category']??0,'orderby'=>'name','hide_empty'=>0 ]); ?>
                <input class="button" type="submit" value="Filter">
            </form>

            <h2>Insert Settings</h2>
			<H3>Remember - not all blocks will work. Make a backup before you run. There is no support if you muck up your database.</H3>
            <label>Choose Block: <input id="bib-block-select" list="bib-block-list" type="text" placeholder="Type to search..." style="width:300px;"></label>
            <datalist id="bib-block-list">
                <?php foreach ( $blocks as $b ) : ?><option value="<?php echo esc_attr( $b ); ?>"></option><?php endforeach; ?>
            </datalist>
            <label style="margin-left:20px;">After Block #: <input type="number" id="bib-block-number" value="2" min="0" style="width:60px;"></label>
            <div id="bib-attrs" style="margin-top:10px;"></div>

            <?php $table = new Bulk_Insert_Block_Table(); $table->prepare_items(); ?>
            <form id="bib-form" method="post"><?php $table->display(); ?></form>
        </div>

        <script>
        (function($){
            var settings = <?php echo wp_json_encode( $settings ); ?>;
            $(function(){
                function showResults(html){ $('#bib-results').html(html); window.scrollTo(0,0); }

                function updateAttrs(){
                    var b = $('#bib-block-select').val();
                    var c = $('#bib-attrs').empty();
                    if(b === 'core/paragraph'){
                        c.append('<label>Paragraph Text:<br><textarea id="bib-attr-content" style="width:100%;height:60px;"></textarea></label>');
                    }
                }
                $('#bib-block-select').on('input change', updateAttrs);
                updateAttrs();

                $(document).on('click','.bib-insert-btn',function(e){
                    e.preventDefault();
                    var pid     = $(this).data('post-id');
                    var block   = $('#bib-block-select').val();
                    var idx     = parseInt($('#bib-block-number').val(),10) + 1;
                    var content = $('#bib-attr-content').val() || '';
                    $.post(settings.ajax_url,{ action:'bib_insert_block', nonce:settings.nonce, post_id:pid, block:block, index:idx, content:content },function(res){
                        if(res.success){
                            var html = '<p>Inserted into post ID: '+res.data+'</p>';
                            if(res.sql) html += '<pre style="background:#f1f1f1;border:1px solid #ccc;padding:8px;">'+res.sql+'</pre>';
                            showResults(html);
                        } else showResults('<p>Error: '+res.data+'</p>');
                    });
                });

                $('#bib-form').on('submit',function(e){
                    var action = $('select[name="action"]').val()||$('select[name="action2"]').val();
                    if(action!=='bulk_insert') return;
                    e.preventDefault();
                    var posts   = $('input[name="post[]"]:checked').map(function(){return $(this).val();}).get();
                    var block   = $('#bib-block-select').val();
                    var idx     = parseInt($('#bib-block-number').val(),10) + 1;
                    var content = $('#bib-attr-content').val() || '';
                    if(!posts.length){ showResults('<p>No posts selected.</p>'); return; }
                    if(!block||settings.blocks.indexOf(block)===-1){ showResults('<p>Select a valid block.</p>'); return; }
                    var toInsert=[], already=[], count=0, start=Date.now();
                    posts.forEach(function(pid){
                        $.post(settings.ajax_url,{ action:'bib_test_block', nonce:settings.nonce, post_id:pid, block:block },function(r){
                            count++;
                            if(r.success){ if(r.data.has) already.push(r.data.title); else toInsert.push({id:pid,title:r.data.title}); }
                            if(count===posts.length){
                                var duration=Date.now()-start;
                                var html='<div style="background:#e6f7ff;padding:15px;border:1px solid #b3d8ff;border-radius:4px;"><h2>Test Results ('+duration+' ms)</h2>';
                                html+='<p><strong>Will insert into:</strong><br>'+(toInsert.length?toInsert.map(i=>i.title).join('<br>'):'None')+'</p>';
                                html+='<p><strong>Already present:</strong><br>'+(already.length?already.join('<br>'):'None')+'</p>';
                                html+='<button id="bib-apply" class="button button-primary">Apply Changes</button></div>';
                                showResults(html);
                                $('#bib-apply').on('click',function(){
                                    var done=0;
                                    toInsert.forEach(function(item){
                                        $.post(settings.ajax_url,{ action:'bib_insert_block', nonce:settings.nonce, post_id:item.id, block:block, index:idx, content:content },function(res){
                                            done++;
                                            if(res.success && res.sql){
                                                $('#bib-results').append('<pre style="background:#f9f9f9;border:1px solid #ccc;padding:8px;">'+res.sql+'</pre>');
                                            }
                                            if(done===toInsert.length) showResults('<p>Inserted into '+done+' posts.</p>');
                                        });
                                    });
                                });
                            }
                        });
                    });
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public function ajax_test_block(){
        check_ajax_referer('bib_nonce','nonce');
        $pid   = intval($_POST['post_id']);
        $block = sanitize_text_field($_POST['block']);
        $post  = get_post($pid);
        if(!$post) wp_send_json_error();
        if($block==='core/paragraph'){ wp_send_json_success(['has'=>false,'title'=>$post->post_title]); }
        $has = has_block($block,$post->post_content);
        wp_send_json_success(['has'=>$has,'title'=>$post->post_title]);
    }

    public function ajax_insert_block(){
        global $wpdb;
        check_ajax_referer('bib_nonce','nonce');
        $pid        = intval($_POST['post_id']);
        $block_name = sanitize_text_field($_POST['block']);
        $index      = max(0,intval($_POST['index']));
        $content    = sanitize_text_field($_POST['content'] ?? '');
        $post       = get_post($pid);
        if(!$post) wp_send_json_error('Post not found');
        if(has_block($block_name,$post->post_content)) wp_send_json_error('Block already present');
        if($block_name==='core/paragraph'){
            $markup = "<!-- wp:core/paragraph -->". $content ."<!-- /wp:core/paragraph -->";
        } else {
            $markup = "<!-- wp:{$block_name} /-->";
        }
        $blocks = parse_blocks($post->post_content);
        $new    = parse_blocks($markup);
        $idx    = min($index,count($blocks));
        $merged = array_merge(array_slice($blocks,0,$idx),$new,array_slice($blocks,$idx));
        // Update and capture SQL
        wp_update_post(['ID'=>$pid,'post_content'=>serialize_blocks($merged)]);
        $sql = $wpdb->last_query;
        wp_send_json_success(['post_id'=>$pid,'sql'=>$sql]);
    }
}

class Bulk_Insert_Block_Table extends WP_List_Table {
    public function __construct(){ parent::__construct(['singular'=>'post','plural'=>'posts','ajax'=>false]); }
    public function get_columns(){ return ['cb'=>'<input type="checkbox"/>','title'=>'Title','date'=>'Date','add_block'=>'Add Block'];}
    public function column_cb($item){ return sprintf('<input type="checkbox" name="post[]" value="%d"/>',$item->ID);}    
    public function column_title($item){ return sprintf('<a href="%s">%s</a>',esc_url(get_edit_post_link($item->ID)),esc_html($item->post_title));}
    public function column_date($item){ return esc_html(mysql2date('Y/m/d',$item->post_date));}
    public function column_add_block($item){ return sprintf('<button class="button bib-insert-btn" data-post-id="%d">Insert</button>',$item->ID);}  
    public function get_bulk_actions(){ return ['bulk_insert'=>'Test before Insert']; }
    public function prepare_items(){
        $this->_column_headers=[ $this->get_columns(),[],[] ];
        $args=['post_type'=>'post','posts_per_page'=>-1,'post_status'=>'publish'];
        if(!empty($_GET['days_old'])) $args['date_query']=[['before'=>sprintf('%d days ago',intval($_GET['days_old'])),'inclusive'=>true]];
        if(!empty($_GET['category'])) $args['cat']=intval($_GET['category']);
        $all = get_posts($args);
        $per = Bulk_Insert_Block_Plugin::PER_PAGE;
        $total = count($all);
        $cur = $this->get_pagenum();
        $this->set_pagination_args(['total_items'=>$total,'per_page'=>$per]);
        $this->items = array_slice($all,($cur-1)*$per,$per);
    }
}

new Bulk_Insert_Block_Plugin();
