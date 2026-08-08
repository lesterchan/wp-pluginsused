/**
 * The `wp-pluginsused/inactive-pluginsused` block.
 *
 * The listing the `[inactive_pluginsused]` shortcode renders, and the sibling of
 * the active one. Two blocks rather than one with a switch, because which
 * listing a post shows is not a setting somebody adjusts -- it is which of two
 * things they inserted, and putting it in the block name keeps it out of an
 * attribute that a later default could quietly flip.
 *
 * It takes no attributes, because the shortcode takes none either.
 *
 * A dynamic block: `save` returns null, so nothing but the block comment is
 * written into post_content and every view re-renders from PHP -- so a plugin
 * hidden on the settings screen disappears from posts published before it was
 * hidden, which a block that saved its markup could not manage.
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
