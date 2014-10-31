<?php

/**
 * Implements a blog post importer from a Wordpress export file.
 */

$this->require_admin ();

$page->layout = 'admin';
$page->title = __ ('Wordpress importer');

$f = new Form ('post');

if ($f->submit ()) {
	if (move_uploaded_file ($_FILES['import_file']['tmp_name'], 'cache/blog_' . $_FILES['import_file']['name'])) {
		$file = 'cache/blog_' . $_FILES['import_file']['name'];
		
		$imported = 0;

		try {
			$posts = new SimpleXMLElement (file_get_contents ($file));
			
			foreach ($posts->channel->item as $entry) {
				$dc = $entry->children ('http://purl.org/dc/elements/1.1/');
				$content = $entry->children ('http://purl.org/rss/1.0/modules/content/');
				$wp = $entry->children ('http://wordpress.org/export/1.2/');
				$published = $wp->status == 'publish' ? 'yes' : 'no';
				$post = array (
					'title' => (string) $entry->title,
					'author' => (string) $dc->creator,
					'ts' => gmdate ('Y-m-d H:i:s', strtotime ($entry->pubDate)),
					'published' => $published,
					'body' => str_replace ("\n", "<br />\n", (string) $content->encoded),
					'tags' => ''
				);
				$sep = '';
				for ($i = 0; $i < count ($entry->category); $i++) {
					$post['tags'] .= $sep . $entry->category[$i]->attributes ()->nicename;
					$sep = ', ';
				}
				$p = new blog\Post ($post);
				if ($p->put ()) {
					Versions::add ($p);

					// add tags
					if ($published == 'yes' && ! empty ($post['tags'])) {
						$tags = explode (',', $post['tags']);
						foreach ($tags as $tag) {
							$tr = trim ($tag);
							DB::execute ('insert into #prefix#blog_tag (id) values (?)', $tr);
							DB::execute (
								'insert into #prefix#blog_post_tag (tag_id, post_id) values (?, ?)',
								$tr,
								$p->id
							);
						}
					}

					$imported++;
				}
			}
			
			echo '<p>' . __ ('Imported %d posts.', $imported) . '</p>';
			echo '<p><a href="/blog/admin">' . __ ('Continue') . '</a></p>';
		} catch (Exception $e) {
			echo '<p><strong>' . __ ('Error importing file') . ': ' . $e->getMessage () . '</strong></p>';
			echo '<p><a href="/blog/admin">' . __ ('Back') . '</a></p>';
		}
		return;
	} else {
		echo '<p><strong>' . __ ('Error uploading file.') . '</strong></p>';
	}
}

$o = new StdClass;

echo $tpl->render ('blog/import/wordpress', $o);
