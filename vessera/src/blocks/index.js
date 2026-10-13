/**
 * Registers the catalog blocks in the editor.
 * Front-end output comes from each block's render.php.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, Placeholder, SelectControl, TextControl, ToggleControl } from '@wordpress/components';

import programMap from '../../blocks/program-map/block.json';
import programDetails from '../../blocks/program-details/block.json';
import courseInfo from '../../blocks/course-info/block.json';
import programList from '../../blocks/program-list/block.json';
import courseDescription from '../../blocks/course-description/block.json';
import programFinder from '../../blocks/program-finder/block.json';
import courseFinder from '../../blocks/course-finder/block.json';
import createEdit from './create-edit';
import useCredentialTerms from '../hooks/use-credential-terms';

function ProgramMapControls( { attributes, setAttributes } ) {
	// Empty outside a program (e.g. in a site template), so only "All" is offered.
	const terms = useCredentialTerms() || [];

	return (
		<PanelBody title={ __( 'Settings', 'vessera' ) }>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Credential', 'vessera' ) }
				help={ __(
					'“All credentials” shows every map that has courses.',
					'vessera'
				) }
				value={ String( attributes.credential ) }
				options={ [
					{ label: __( 'All credentials', 'vessera' ), value: '0' },
					...terms.map( ( term ) => ( { label: term.name, value: String( term.id ) } ) ),
				] }
				onChange={ ( value ) => setAttributes( { credential: parseInt( value, 10 ) } ) }
			/>
			{ attributes.credential === 0 && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Show multiple credentials as', 'vessera' ) }
					help={ __(
						'Tabs appear on the published page. The editor preview always shows the maps stacked.',
						'vessera'
					) }
					value={ attributes.display }
					options={ [
						{ label: __( 'Tabs', 'vessera' ), value: 'tabs' },
						{ label: __( 'Stacked', 'vessera' ), value: 'stacked' },
					] }
					onChange={ ( display ) => setAttributes( { display } ) }
				/>
			) }
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Credential heading level', 'vessera' ) }
				value={ String( attributes.headingLevel ) }
				options={ [ 2, 3, 4, 5, 6 ].map( ( level ) => ( {
					label: `H${ level }`,
					value: String( level ),
				} ) ) }
				onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Semesters per row', 'vessera' ) }
				help={ __(
					'Fewer per row gives each semester more room, so titles wrap less. Small screens always show one per row.',
					'vessera'
				) }
				value={ String( attributes.columns ?? 0 ) }
				options={ [
					{ label: __( 'As many as fit', 'vessera' ), value: '0' },
					{ label: '1', value: '1' },
					{ label: '2', value: '2' },
					{ label: '3', value: '3' },
					{ label: '4', value: '4' },
				] }
				onChange={ ( value ) => setAttributes( { columns: parseInt( value, 10 ) } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Link courses to their pages', 'vessera' ) }
				checked={ attributes.linkCourses }
				onChange={ ( linkCourses ) => setAttributes( { linkCourses } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Show term credit totals', 'vessera' ) }
				checked={ attributes.showTermTotals }
				onChange={ ( showTermTotals ) => setAttributes( { showTermTotals } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( programMap, {
	edit: createEdit( {
		metadata: programMap,
		postType: 'vessera_program',
		emptyMessage: __(
			'No program map yet. Build one in the Credentials panel in the sidebar, then update the program to see it here.',
			'vessera'
		),
		Controls: ProgramMapControls,
	} ),
} );

registerBlockType( programDetails, {
	edit: createEdit( {
		metadata: programDetails,
		postType: 'vessera_program',
		emptyMessage: __(
			'No details yet. Tick credential types and fill in the Credentials panel in the sidebar, then update the program.',
			'vessera'
		),
	} ),
} );

registerBlockType( courseInfo, {
	edit: createEdit( {
		metadata: courseInfo,
		postType: 'vessera_course',
		emptyMessage: __(
			'Fill in the Course Details panel in the sidebar, then update the course to see it here.',
			'vessera'
		),
		Controls: ( { attributes, setAttributes } ) => (
			<PanelBody title={ __( 'Settings', 'vessera' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Link prerequisites to their pages', 'vessera' ) }
					checked={ attributes.linkPrerequisites }
					onChange={ ( linkPrerequisites ) => setAttributes( { linkPrerequisites } ) }
				/>
			</PanelBody>
		),
	} ),
} );

const LIST_TYPES = {
	outcomes: {
		title: __( 'Program Outcomes', 'vessera' ),
		heading: __( 'Program outcomes', 'vessera' ),
		icon: 'yes-alt',
	},
	careers: {
		title: __( 'Careers', 'vessera' ),
		heading: __( 'Career opportunities', 'vessera' ),
		icon: 'businessperson',
	},
	certifications: {
		title: __( 'Industry Certifications', 'vessera' ),
		heading: __( 'Industry certifications', 'vessera' ),
		icon: 'awards',
	},
};

function ProgramListControls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Settings', 'vessera' ) }>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'List', 'vessera' ) }
				value={ attributes.list }
				options={ Object.entries( LIST_TYPES ).map( ( [ value, type ] ) => ( {
					label: type.title,
					value,
				} ) ) }
				onChange={ ( list ) => setAttributes( { list } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Heading', 'vessera' ) }
				help={ __( 'Leave empty for the default. The heading only appears when the list has items.', 'vessera' ) }
				placeholder={ LIST_TYPES[ attributes.list ]?.heading }
				value={ attributes.heading }
				onChange={ ( heading ) => setAttributes( { heading } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Heading level', 'vessera' ) }
				value={ String( attributes.headingLevel ) }
				options={ [ 2, 3, 4, 5, 6 ].map( ( level ) => ( { label: `H${ level }`, value: String( level ) } ) ) }
				onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( programList, {
	edit: createEdit( {
		metadata: programList,
		postType: 'vessera_program',
		emptyMessage: __(
			'This list is empty, so nothing will show on the page. Add items in the Outcomes & Careers panel in the sidebar, then update the program.',
			'vessera'
		),
		Controls: ProgramListControls,
	} ),
	// Separate entries in the inserter for each list.
	variations: Object.entries( LIST_TYPES ).map( ( [ list, type ], i ) => ( {
		name: list,
		title: type.title,
		icon: type.icon,
		attributes: { list },
		isDefault: i === 0,
		isActive: [ 'list' ],
		scope: [ 'inserter', 'transform' ],
	} ) ),
} );

registerBlockType( courseDescription, {
	edit: createEdit( {
		metadata: courseDescription,
		postType: 'vessera_course',
		emptyMessage: __(
			'Add a description in the Course Details panel in the sidebar, then update the course to see it here.',
			'vessera'
		),
	} ),
} );

// Finders list every published item, so they don't depend on the current post.
function createFinderEdit( { metadata, toggles, emptyMessage, headingLevels } ) {
	return function Edit( { attributes, setAttributes } ) {
		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Show', 'vessera' ) }>
						{ toggles.map( ( [ key, label ] ) => (
							<ToggleControl
								key={ key }
								__nextHasNoMarginBottom
								label={ label }
								checked={ attributes[ key ] }
								onChange={ ( value ) => setAttributes( { [ key ]: value } ) }
							/>
						) ) }
						<p className="components-base-control__help">
							{ __(
								'A filter only appears when it has options to choose from.',
								'vessera'
							) }
						</p>
					</PanelBody>
					{ headingLevels && (
						<PanelBody title={ __( 'Settings', 'vessera' ) }>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Title heading level', 'vessera' ) }
								value={ String( attributes.headingLevel ) }
								options={ headingLevels.map( ( level ) => ( {
									label: `H${ level }`,
									value: String( level ),
								} ) ) }
								onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) } ) }
							/>
						</PanelBody>
					) }
				</InspectorControls>
				<div { ...useBlockProps() }>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						EmptyResponsePlaceholder={ () => (
							<Placeholder label={ metadata.title } instructions={ emptyMessage } />
						) }
					/>
				</div>
			</>
		);
	};
}

registerBlockType( programFinder, {
	edit: createFinderEdit( {
		metadata: programFinder,
		headingLevels: [ 2, 3, 4 ],
		emptyMessage: __( 'No published programs yet. Published programs will be listed here.', 'vessera' ),
		toggles: [
			[ 'showSearch', __( 'Search box', 'vessera' ) ],
			[ 'showCredentialFilter', __( 'Credential filter', 'vessera' ) ],
			[ 'showCareerFilter', __( 'Career area filter', 'vessera' ) ],
			[ 'showDeliveryFilter', __( 'Delivery filter', 'vessera' ) ],
			[ 'showDepartmentFilter', __( 'Department filter', 'vessera' ) ],
			[ 'showExcerpt', __( 'Program excerpts', 'vessera' ) ],
		],
	} ),
} );

registerBlockType( courseFinder, {
	edit: createFinderEdit( {
		metadata: courseFinder,
		emptyMessage: __( 'No published courses yet. Published courses will be listed here.', 'vessera' ),
		toggles: [
			[ 'showSearch', __( 'Search box', 'vessera' ) ],
			[ 'showDepartmentFilter', __( 'Department filter', 'vessera' ) ],
			[ 'showProgramFilter', __( 'Program filter', 'vessera' ) ],
			[ 'showDepartmentColumn', __( 'Department column', 'vessera' ) ],
			[ 'linkCourses', __( 'Link courses to their pages', 'vessera' ) ],
		],
	} ),
} );
