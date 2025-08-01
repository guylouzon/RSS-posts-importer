<?php

if (!isset($ajax_add)) {
    $ajax_add = false;
}
if (!isset($ajax_edit)) {
    $ajax_edit = false;
}

$show = '';
if (!isset($f)) {
    $f = [
        'id' => $ajax_feed_id ?? 0,
        'name' => 'New feed',
        'url' => '',
        'max_posts' => 5,
        'author_id' => 1,
        'category_id' => 1,
        'tags_id' => [],
        'strip_html' => 'false',
        'nofollow_outbound' => 'false',
        'automatic_import_categories' => 'false',
        'automatic_import_author' => 'false',
        'canonical_urls' => 'my_blog'
    ];
    $show = ' show';
}

$tag = '';
$tagarray = [];
if (is_array($f['tags_id'])) {
    if (!empty($f['tags_id'])) {
        foreach ($f['tags_id'] as $tag_id) {
            $tagname = get_tag($tag_id);
            if ($tagname && isset($tagname->name)) {
                $tagarray[] = $tagname->name;
            }
        }
        $tag = join(',', $tagarray);
    } else {
        $tag = '';
    }
} else {
    if (empty($f['tags_id'])) {
        $f['tags_id'] = [];
        $tag = '';
    } else {
        $f['tags_id'] = [$f['tags_id']];
        $tagname = get_tag(intval($f['tags_id'][0]));
        $tag = $tagname && isset($tagname->name) ? $tagname->name : '';
    }
}

$category = '';
$catarray = [];
if (is_array($f['category_id'])) {
    foreach ($f['category_id'] as $cat) {
        $catarray[] = get_cat_name($cat);
    }
    $category = join(',', $catarray);
} else {
    if (empty($f['category_id'])) {
        $f['category_id'] = [1];
        $category = get_the_category_by_ID(1);
    } else {
        $f['category_id'] = [$f['category_id']];
        $category = get_the_category_by_ID(intval($f['category_id'][0]));
    }
}

?>

<?php
if ($ajax_add || !$ajax_edit):
?>
<tr id="display_<?php echo ($f['id']); ?>" class="data-row<?php echo $show; ?>" data-fields="name,url,max_posts">
    <td class="rss_pi-feed_name">
        <strong><a href="#" class="edit_<?php echo ($f['id']); ?> toggle-edit" data-target="<?php echo ($f['id']); ?>"><span class="field-name"><?php echo esc_html(stripslashes($f['name'])); ?></span></a></strong>
        <div class="row-options">
            <?php
            if (isset($f['feed_status'])): ?>
            <a href="#" class="edit_<?php echo ($f['id']); ?> toggle-edit" data-target="<?php echo ($f['id']); ?>"><?php _e('Edit', 'rss-post-importer'); ?></a> |
            <?php
            endif;
            ?>
            <a href="#" class="delete-row" data-target="<?php echo ($f['id']); ?>"><?php _e('Delete', 'rss-post-importer'); ?></a>
            <?php
            if (isset($f['feed_status']) && $f['feed_status'] == "active") { ?>
            | <a href="#" class="status-row" data-action="pause" data-target="<?php echo ($f['id']); ?>"><?php _e('Pause', 'rss-post-importer'); ?></a>
            <?php } elseif (isset($f['feed_status']) && $f['feed_status'] == "pause") { ?>
            | <a href="#" class="status-row" data-action="enable" data-target="<?php echo ($f['id']); ?>"><?php _e('Enable Feed', 'rss-post-importer'); ?></a>
            <?php } ?>
        </div>
    </td>
    <td class="rss_pi-feed_url"><span class="field-url"><?php echo esc_url(stripslashes($f['url'])); ?></span></td>
    <td class="rss_pi_feed_max_posts"><span class="field-max_posts"><?php echo $f['max_posts']; ?></span></td>
   <!-- <td width="20%"><?php //echo $category;  ?></td>-->
</tr>
<?php
endif;
?>

<?php
if ($ajax_add || $ajax_edit):
?>
<tr id="edit_<?php echo ($f['id']); ?>" class="edit-row<?php echo $show; ?>">
    <td colspan="4">
        <table class="widefat edit-table">
            <tr>
                <td><label for="<?php echo ($f['id']); ?>-name"><?php _e("Feed name", 'rss-post-importer'); ?></label></td>
                <td>
                    <input type="text" class="field-name" name="<?php echo ($f['id']); ?>-name" id="<?php echo ($f['id']); ?>-name" value="<?php echo esc_attr(stripslashes($f['name'])); ?>" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="<?php echo ($f['id']); ?>-url"><?php _e("Feed url", 'rss-post-importer'); ?></label>
                    <p class="description">e.g. "http://news.google.com/?output=rss"</p>
                </td>
                <td><input type="text" class="field-url" name="<?php echo ($f['id']); ?>-url" id="<?php echo ($f['id']); ?>-url" value="<?php echo esc_attr(stripslashes($f['url'])); ?>" /></td>
            </tr>
            <tr>
                <td><label for="<?php echo ($f['id']); ?>-max_posts"><?php _e("Max posts / import", 'rss-post-importer'); ?></label></td>
                <td><input type="number" class="field-max_posts" name="<?php echo ($f['id']); ?>-max_posts" id="<?php echo ($f['id']); ?>-max_posts" value="<?php echo ($f['max_posts']); ?>" min="1" max="1000" /></td>
            </tr>
            <tr>
                <td>
                    <label for="<?php echo ($f['id']); ?>-nofollow_outbound"><?php _e('Nofollow option for all outbound links?', "rss-post-importer"); ?></label>
                    <p class="description"><?php _e('Add rel="nofollow" to all outbounded links.', "rss-post-importer"); ?></p>
                </td>
                <td>
                    <ul class="radiolist">
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-nofollow_outbound_true" name="<?php echo($f['id']); ?>-nofollow_outbound" value="true" <?php echo($f['nofollow_outbound'] == 'true' ? 'checked="checked"' : ''); ?> /> <?php _e('Yes', 'rss-post-importer'); ?></label>
                        </li>
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-nofollow_outbound_false" name="<?php echo($f['id']); ?>-nofollow_outbound" value="false" <?php echo($f['nofollow_outbound'] == 'false' || $f['nofollow_outbound'] == '' ? 'checked="checked"' : ''); ?> /> <?php _e('No', 'rss-post-importer'); ?></label>
                        </li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="<?php echo ($f['id']); ?>-canonical_urls"><?php _e('SEO canonical URLs ?', "rss-post-importer"); ?></label>
                </td>
                <td>
                    <ul class="radiolist">
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-canonical_urls_myblog" name="<?php echo($f['id']); ?>-canonical_urls" value="my_blog" <?php echo($f['canonical_urls'] == 'my_blog' || $f['canonical_urls'] == '' ? 'checked="checked"' : ''); ?> /> <?php _e('My Blog URLs', 'rss-post-importer'); ?></label>
                        </li>
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-canonical_urls_sourceblog" name="<?php echo($f['id']); ?>-canonical_urls" value="source_blog" <?php echo($f['canonical_urls'] == 'source_blog' ? 'checked="checked"' : ''); ?> /> <?php _e('Source Blog URLs', 'rss-post-importer'); ?></label>
                        </li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="<?php echo ($f['id']); ?>-automatic_import_author"><?php _e('Automatic import of Authors ?', "rss-post-importer"); ?></label>
                </td>
                <td>
                    <ul class="radiolist">
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-automatic_import_author_true" name="<?php echo($f['id']); ?>-automatic_import_author" value="true" <?php echo($f['automatic_import_author'] == 'true' ? 'checked="checked"' : ''); ?> /> <?php _e('Yes', 'rss-post-importer'); ?></label>
                        </li>
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-automatic_import_author_false" name="<?php echo($f['id']); ?>-automatic_import_author" value="false" <?php echo($f['automatic_import_author'] == 'false' || $f['automatic_import_author'] == '' ? 'checked="checked"' : ''); ?> /> <?php _e('No', 'rss-post-importer'); ?></label>
                        </li>
                    </ul>
                </td>
            </tr>
  
            <tr>
                <td>
                    <label for="<?php echo ($f['id']); ?>-automatic_import_categories"><?php _e('Automatic import of Categories ?', "rss-post-importer"); ?></label>
                </td>
                <td>
                    <ul class="radiolist">
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-automatic_import_categories_true" name="<?php echo($f['id']); ?>-automatic_import_categories" value="true" <?php echo($f['automatic_import_categories'] == 'true' ? 'checked="checked"' : ''); ?> /> <?php _e('Yes', 'rss-post-importer'); ?></label>
                        </li>
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-automatic_import_categories_false" name="<?php echo($f['id']); ?>-automatic_import_categories" value="false" <?php echo($f['automatic_import_categories'] == 'false' || $f['automatic_import_categories'] == '' ? 'checked="checked"' : ''); ?> /> <?php _e('No', 'rss-post-importer'); ?></label>
                        </li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td><label for=""><?php _e("Category", 'rss-post-importer'); ?></label></td>
                <td>
                    <?php
                    $rss_post_pi_admin = new rssPIAdmin();
                    ?>
                        <div class="category_container">
                            <ul>
                                <?php
                                $allcats = $rss_post_pi_admin->wp_category_checklist_rss_pi(0, false, $f['category_id']);
                                $allcats = str_replace('name="post_category[]"', 'name="' . $f['id'] . '-category_id[]"', $allcats);
                                echo $allcats;
                                ?>
                            </ul>
                        </div>
                </td>
            </tr>
            <tr>
                <td><label for=""><?php _e("Tags", 'rss-post-importer'); ?></label></td>
                <td>
                    <div class="tags_container">
                        <?php
                        echo $rss_post_pi_admin->rss_pi_tags_checkboxes($f['id'], $f['tags_id']);
                        ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td><label for=""><?php _e("Strip html tags", 'rss-post-importer'); ?></label></td>
                <td>
                    <ul class="radiolist">
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-strip_html" name="<?php echo($f['id']); ?>-strip_html" value="true" <?php echo($f['strip_html'] == 'true' ? 'checked="checked"' : ''); ?> /> <?php _e('Yes', 'rss-post-importer'); ?></label>
                        </li>
                        <li>
                            <label><input type="radio" id="<?php echo($f['id']); ?>-strip_html" name="<?php echo($f['id']); ?>-strip_html" value="false" <?php echo($f['strip_html'] == 'false' ? 'checked="checked"' : ''); ?> /> <?php _e('No', 'rss-post-importer'); ?></label>
                        </li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td><input type="hidden" name="id" value="<?php echo($f['id']); ?>" /></td>
                <td><a id="close-edit-table-<?php echo($f['id']); ?>" class="button button-large toggle-edit" data-target="<?php echo ($f['id']); ?>"><?php _e('Close', 'rss-post-importer'); ?></a></td>
            </tr>
        </table>
    </td>
</tr>
<?php
endif;
?>
