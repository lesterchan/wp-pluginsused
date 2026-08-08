/**
 * The `wp-pluginsused/stats-pluginsused` block.
 *
 * The summary sentence the `[stats_pluginsused]` shortcode renders. It takes no
 * attributes, because the shortcode takes none either -- the listing is a
 * reading of wp-content/plugins taken at render time, so there is nothing for an
 * author to configure on the block.
 *
 * A dynamic block: `save` returns null, so nothing but the block comment is
 * written into post_content and every view re-renders from PHP. That is what
 * lets the block and the shortcode share one renderer, and it is also why the
 * counts cannot go stale the way a saved sentence would the moment a plugin is
 * activated.
 *
 * The block name is hyphenated where the shortcode is underscored: a block name
 * must match [a-z0-9-] and an underscore is not allowed in one. That is the only
 * reason the two spellings differ.
 *
 * The preview is core's ServerSideRender, which posts to
 * /wp/v2/block-renderer/wp-pluginsused/stats-pluginsused and draws what the
 * front end would draw. That is also why this block registers no REST route of
 * its own.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

/**
 * The editor view.
 *
 * Capitalised and named rather than an `edit()` shorthand because useBlockProps
 * is a React hook, and the hook rules identify a component by that capital.
 *
 * No `inert` wrapper here, unlike the two listing blocks: the summary is three
 * phrases and a couple of `<strong>` tags, with nothing in it to click.
 *
 * @return {Element} The editor view.
 */
function Edit() {
	return (
		<div { ...useBlockProps() }>
			<ServerSideRender block={ metadata.name } />
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
