/**
 * The `wp-pluginsused/active-pluginsused` block.
 *
 * The listing the `[active_pluginsused]` shortcode renders. It takes no
 * attributes, because the shortcode takes none either -- which plugins appear is
 * decided by the settings screen and by the hidden-plugins filter, not by the
 * post. A block attribute here would be a second place to hide a plugin, and one
 * of them would eventually disagree with the other.
 *
 * A dynamic block: `save` returns null, so nothing but the block comment is
 * written into post_content and every view re-renders from PHP. That matters
 * more here than it does for most blocks: a plugin hidden on the settings screen
 * has to disappear from posts that were published before it was hidden, and a
 * block that had saved its markup would go on advertising it.
 *
 * The block name is hyphenated where the shortcode is underscored: a block name
 * must match [a-z0-9-] and an underscore is not allowed in one. That is the only
 * reason the two spellings differ.
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
 * @return {Element} The editor view.
 */
function Edit() {
	return (
		<div { ...useBlockProps() }>
			{ /* Every entry carries two outbound links, to the plugin and to its
			     author. In the editor a click there should select the block, not
			     take the author off to somebody else's site mid-edit. */ }
			<div inert="">
				<ServerSideRender block={ metadata.name } />
			</div>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
