/**
 * Editor entry point: adds Program and Course detail panels
 * to the document sidebar of the block editor.
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';

import CourseFields from './components/course-fields';
import ProgramFields from './components/program-fields';
import CredentialsPanel from './components/credentials-panel';
import ProgramLists from './components/program-lists';

import './editor.scss';

function CatalogPanels() {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	const { removeEditorPanel } = useDispatch( editorStore );

	// Credentials are chosen at the top of the Credentials panel, so the
	// separate Credential Types panel would only be a duplicate.
	useEffect( () => {
		if ( postType === 'vessera_program' && removeEditorPanel ) {
			removeEditorPanel( 'taxonomy-panel-vessera_credential' );
		}
	}, [ postType, removeEditorPanel ] );

	if ( postType === 'vessera_course' ) {
		return (
			<PluginDocumentSettingPanel
				name="vessera-course-details"
				title={ __( 'Course Details', 'vessera' ) }
			>
				<CourseFields />
			</PluginDocumentSettingPanel>
		);
	}

	if ( postType === 'vessera_program' ) {
		return (
			<>
				<PluginDocumentSettingPanel
					name="vessera-program-details"
					title={ __( 'Program Details', 'vessera' ) }
				>
					<ProgramFields />
				</PluginDocumentSettingPanel>
				<PluginDocumentSettingPanel
					name="vessera-program-credentials"
					title={ __( 'Credentials', 'vessera' ) }
				>
					<CredentialsPanel />
				</PluginDocumentSettingPanel>
				<PluginDocumentSettingPanel
					name="vessera-program-lists"
					title={ __( 'Outcomes & Careers', 'vessera' ) }
				>
					<ProgramLists />
				</PluginDocumentSettingPanel>
			</>
		);
	}

	return null;
}

registerPlugin( 'vessera-panels', { render: CatalogPanels } );
