/**
 * Course import screen: upload → match columns → preview → import → results.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	TextControl,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';

import readSpreadsheet from './parse';
import buildPlan, { duplicateGroups } from './plan';
import { columnLetter, detectHeaderRow, guessMapping, prefixOf, tableFromGrid } from './utils';

const BATCH_SIZE = 25;
const MAPPING_KEY = 'vesseraImportMapping';

const FIELDS = [
	{ key: 'code', label: __( 'Course code', 'vessera' ), required: true, help: __( 'Used to match existing courses, so re-importing updates instead of duplicating.', 'vessera' ) },
	{ key: 'subject', label: __( 'Subject prefix', 'vessera' ), help: __( 'Only if the code is split across two columns, like “CPD” and “153”.', 'vessera' ) },
	{ key: 'title', label: __( 'Title', 'vessera' ), required: true },
	{ key: 'credits', label: __( 'Credit hours', 'vessera' ) },
	{ key: 'lectureHours', label: __( 'Lecture hours', 'vessera' ) },
	{ key: 'labHours', label: __( 'Lab hours', 'vessera' ) },
	{ key: 'contactHours', label: __( 'Contact hours', 'vessera' ) },
	{ key: 'description', label: __( 'Description', 'vessera' ) },
	{ key: 'prerequisites', label: __( 'Prerequisites', 'vessera' ), help: __( 'Course codes are matched to courses; any other wording goes into the prerequisite notes.', 'vessera' ) },
	{ key: 'notes', label: __( 'Prerequisite notes', 'vessera' ) },
	{ key: 'department', label: __( 'Department', 'vessera' ), help: __( 'New department names are created automatically.', 'vessera' ) },
];

const ACTION_LABELS = {
	create: __( 'Create', 'vessera' ),
	update: __( 'Update', 'vessera' ),
	skip: __( 'Skip', 'vessera' ),
	duplicate: __( 'Duplicate', 'vessera' ),
	error: __( 'Error', 'vessera' ),
};

function loadSavedMapping() {
	try {
		return JSON.parse( window.localStorage.getItem( MAPPING_KEY ) ) || {};
	} catch ( e ) {
		return {};
	}
}

function saveMapping( mapping, headers ) {
	const byName = Object.fromEntries(
		Object.entries( mapping ).map( ( [ field, index ] ) => [ field, headers[ index ] ] )
	);
	try {
		window.localStorage.setItem( MAPPING_KEY, JSON.stringify( byName ) );
	} catch ( e ) {
		// Private browsing or storage full: not worth interrupting the import.
	}
}

export default function App( { coursesUrl, canPublish } ) {
	const [ step, setStep ] = useState( 'upload' );
	const [ file, setFile ] = useState( null );
	const [ table, setTable ] = useState( null );
	const [ mapping, setMapping ] = useState( {} );
	const [ options, setOptions ] = useState( { status: 'draft', existing: 'update' } );
	const [ existing, setExisting ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ progress, setProgress ] = useState( { done: 0, total: 0 } );
	const [ results, setResults ] = useState( null );
	const [ choices, setChoices ] = useState( {} ); // duplicate code key => row number, 0 = none
	const [ departments, setDepartments ] = useState( [] ); // [ { id, name, prefixes } ]
	const [ prefixChoices, setPrefixChoices ] = useState( {} ); // "CPD" => term ID, 0 = none
	const [ newDepartment, setNewDepartment ] = useState( '' );

	// Course prefixes in the file, with how many rows use each: { CPD: 14, WELD: 6 }.
	const prefixCounts = useMemo( () => {
		const counts = {};
		if ( table && 'code' in mapping ) {
			table.rows.forEach( ( row ) => {
				const subject = 'subject' in mapping ? row.cells[ mapping.subject ] : '';
				const prefix = prefixOf( subject || row.cells[ mapping.code ] );
				if ( prefix ) {
					counts[ prefix ] = ( counts[ prefix ] || 0 ) + 1;
				}
			} );
		}
		return counts;
	}, [ table, mapping ] );

	// The user's choice for each prefix, else the department that already claims it.
	const prefixDepartmentIds = useMemo( () => {
		const ids = {};
		Object.keys( prefixCounts ).forEach( ( prefix ) => {
			if ( prefix in prefixChoices ) {
				ids[ prefix ] = prefixChoices[ prefix ];
			} else {
				const owner = departments.find( ( d ) => d.prefixes.includes( prefix ) );
				ids[ prefix ] = owner ? owner.id : 0;
			}
		} );
		return ids;
	}, [ prefixCounts, prefixChoices, departments ] );

	const prefixDepartments = useMemo(
		() =>
			Object.fromEntries(
				Object.entries( prefixDepartmentIds )
					.map( ( [ prefix, id ] ) => [ prefix, departments.find( ( d ) => d.id === id ) ] )
					.filter( ( [ , department ] ) => department )
			),
		[ prefixDepartmentIds, departments ]
	);

	const plan = useMemo(
		() =>
			table && existing
				? buildPlan( table, mapping, existing, options, choices, prefixDepartments )
				: [],
		[ table, mapping, existing, options, choices, prefixDepartments ]
	);
	const groups = useMemo( () => duplicateGroups( plan ), [ plan ] );

	const counts = plan.reduce( ( acc, item ) => {
		acc[ item.action ] = ( acc[ item.action ] || 0 ) + 1;
		return acc;
	}, {} );

	// ---- Step 1: read the file -------------------------------------------

	const loadFile = async ( nextFile, sheet ) => {
		setError( '' );
		setBusy( true );
		try {
			const data = await readSpreadsheet( nextFile, sheet );
			const headerIndex = detectHeaderRow( data.grid );
			if ( headerIndex === -1 ) {
				throw new Error( 'empty' );
			}
			setFile( nextFile );
			applyHeaderRow( data, headerIndex );
			setPrefixChoices( {} );
			try {
				setDepartments( await apiFetch( { path: '/vessera/v1/departments' } ) );
			} catch ( e ) {
				setDepartments( [] ); // The import still works without prefix matching.
			}
			setStep( 'map' );
		} catch ( e ) {
			setError(
				e.message === 'unsupported'
					? __( 'Please choose a .csv or .xlsx file.', 'vessera' )
					: __( 'That file couldn’t be read, or it’s empty.', 'vessera' )
			);
		}
		setBusy( false );
	};

	// Rebuild headers and course rows from a chosen header row, then re-guess the mapping.
	const applyHeaderRow = ( data, headerIndex ) => {
		const next = { ...data, headerIndex, ...tableFromGrid( data.grid, headerIndex ) };
		setTable( next );
		setChoices( {} );
		setMapping( guessMapping( next.headers, loadSavedMapping() ) );
	};

	const addDepartment = async () => {
		const name = newDepartment.trim();
		if ( ! name ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			const term = await apiFetch( {
				path: '/wp/v2/vessera_department',
				method: 'POST',
				data: { name },
			} );
			setDepartments(
				[ ...departments, { id: term.id, name: term.name, prefixes: [] } ].sort( ( a, b ) =>
					a.name.localeCompare( b.name )
				)
			);
			setNewDepartment( '' );
		} catch ( e ) {
			setError( e.message || __( 'The department couldn’t be created.', 'vessera' ) );
		}
		setBusy( false );
	};

	// ---- Step 2 → 3: load existing codes and preview ----------------------

	const toPreview = async () => {
		setError( '' );
		setBusy( true );
		try {
			saveMapping( mapping, table.headers );
			setExisting( await apiFetch( { path: '/vessera/v1/course-codes' } ) );
			setStep( 'preview' );
		} catch ( e ) {
			setError( e.message || __( 'Existing courses couldn’t be loaded.', 'vessera' ) );
		}
		setBusy( false );
	};

	// ---- Step 4: import in batches, then prerequisites --------------------

	const runImport = async () => {
		const todo = plan.filter( ( item ) => item.action === 'create' || item.action === 'update' );
		const withPrereqs = todo.filter( ( item ) => item.prereqs );
		const outcome = { created: 0, updated: 0, skipped: 0, errors: [], unresolved: [] };

		// Rows rejected in the preview are reported too.
		plan.forEach( ( item ) => {
			if ( item.action === 'error' ) {
				outcome.errors.push( { row: item.rowNumber, message: item.messages.join( ' ' ) } );
			} else if ( item.action === 'skip' ) {
				outcome.skipped++;
			}
		} );

		setStep( 'importing' );
		setProgress( { done: 0, total: todo.length + withPrereqs.length } );
		let done = 0;

		// Remember prefix → department choices for next time and for hand-made courses.
		if ( Object.keys( prefixDepartmentIds ).length ) {
			try {
				await apiFetch( {
					path: '/vessera/v1/department-prefixes',
					method: 'POST',
					data: { assignments: prefixDepartmentIds },
				} );
			} catch ( e ) {
				// Users who can't manage departments still get this import's assignments.
			}
		}

		const imported = new Set();
		for ( let i = 0; i < todo.length; i += BATCH_SIZE ) {
			const batch = todo.slice( i, i + BATCH_SIZE );
			try {
				const response = await apiFetch( {
					path: '/vessera/v1/import/courses',
					method: 'POST',
					data: { rows: batch.map( ( item ) => item.payload ), ...options },
				} );
				response.results.forEach( ( result ) => {
					if ( result.status === 'created' || result.status === 'updated' ) {
						outcome[ result.status ]++;
						imported.add( result.row );
					} else if ( result.status === 'skipped' ) {
						outcome.skipped++;
					} else {
						outcome.errors.push( { row: result.row, message: result.message } );
					}
				} );
			} catch ( e ) {
				batch.forEach( ( item ) =>
					outcome.errors.push( { row: item.rowNumber, message: e.message } )
				);
			}
			done += batch.length;
			setProgress( ( p ) => ( { ...p, done } ) );
		}

		// Second pass: every course now exists, so prerequisite codes can be matched.
		const prereqRows = withPrereqs.filter( ( item ) => imported.has( item.rowNumber ) );
		done += withPrereqs.length - prereqRows.length;
		for ( let i = 0; i < prereqRows.length; i += BATCH_SIZE ) {
			const batch = prereqRows.slice( i, i + BATCH_SIZE );
			try {
				const response = await apiFetch( {
					path: '/vessera/v1/import/prerequisites',
					method: 'POST',
					data: {
						rows: batch.map( ( item ) => ( {
							row: item.rowNumber,
							code: item.code,
							...item.prereqs,
						} ) ),
					},
				} );
				outcome.unresolved.push( ...response.unresolved );
			} catch ( e ) {
				batch.forEach( ( item ) =>
					outcome.errors.push( {
						row: item.rowNumber,
						/* translators: %s: error message */
						message: sprintf( __( 'Prerequisites not set: %s', 'vessera' ), e.message ),
					} )
				);
			}
			done += batch.length;
			setProgress( ( p ) => ( { ...p, done } ) );
		}

		outcome.errors.sort( ( a, b ) => a.row - b.row );
		setResults( outcome );
		setStep( 'done' );
	};

	const startOver = () => {
		setStep( 'upload' );
		setFile( null );
		setTable( null );
		setExisting( null );
		setResults( null );
		setChoices( {} );
		setPrefixChoices( {} );
		setError( '' );
	};

	const errorNotice = error && (
		<Notice status="error" isDismissible={ false }>
			{ error }
		</Notice>
	);

	// ---- Screens ----------------------------------------------------------

	if ( step === 'upload' ) {
		return (
			<Card className="vessera-import">
				<CardHeader>
					<h2>{ __( '1. Choose a spreadsheet', 'vessera' ) }</h2>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 4 }>
						{ errorNotice }
						<p>
							{ __(
								'Upload a .csv or .xlsx file with one course per row and a row of column headers. The file is read in your browser; nothing is saved until you confirm the import.',
								'vessera'
							) }
						</p>
						<input
							type="file"
							accept=".csv,.xlsx"
							aria-label={ __( 'Spreadsheet file', 'vessera' ) }
							disabled={ busy }
							onChange={ ( event ) => event.target.files[ 0 ] && loadFile( event.target.files[ 0 ] ) }
						/>
						{ busy && <Spinner /> }
					</VStack>
				</CardBody>
			</Card>
		);
	}

	if ( step === 'map' ) {
		// "C: Course Title", or just "C" when the header cell is empty.
		const columnOptions = [
			{ label: __( '— Don’t import —', 'vessera' ), value: '' },
			...table.headers.map( ( header, i ) => {
				const letter = columnLetter( i );
				return { label: header === letter ? letter : `${ letter }: ${ header }`, value: String( i ) };
			} ),
		];

		// Offer the first 20 non-empty rows as possible header rows.
		const headerRowOptions = table.grid
			.map( ( row, index ) => ( { row, index } ) )
			.filter( ( { row } ) => row.some( ( cell ) => cell !== '' ) )
			.slice( 0, 20 )
			.map( ( { row, index } ) => {
				const preview = row.filter( ( cell ) => cell !== '' ).join( ', ' );
				return {
					value: String( index ),
					label: sprintf(
						/* translators: 1: row number, 2: the row's cell values */
						__( 'Row %1$d: %2$s', 'vessera' ),
						index + 1,
						preview.length > 70 ? preview.slice( 0, 70 ) + '…' : preview
					),
				};
			} );

		const sample = table.rows[ 0 ];
		const ready = 'code' in mapping && 'title' in mapping && table.rows.length > 0;

		return (
			<Card className="vessera-import">
				<CardHeader>
					<h2>{ __( '2. Match columns to course fields', 'vessera' ) }</h2>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 5 }>
						{ errorNotice }
						<p>
							{ sprintf(
								/* translators: 1: file name, 2: number of rows */
								_n( '%1$s: %2$d course row.', '%1$s: %2$d course rows.', table.rows.length, 'vessera' ),
								file.name,
								table.rows.length
							) }
						</p>

						{ table.sheets.length > 1 && (
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Sheet', 'vessera' ) }
								value={ table.sheet }
								options={ table.sheets.map( ( s ) => ( { label: s, value: s } ) ) }
								onChange={ ( sheet ) => loadFile( file, sheet ) }
							/>
						) }

						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Header row', 'vessera' ) }
							help={ __(
								'The row with the column names. Rows above it, like a title or date, are ignored.',
								'vessera'
							) }
							value={ String( table.headerIndex ) }
							options={ headerRowOptions }
							onChange={ ( value ) => applyHeaderRow( table, parseInt( value, 10 ) ) }
						/>

						{ ! table.rows.length && (
							<Notice status="warning" isDismissible={ false }>
								{ __( 'There are no course rows below this header row. Choose a different header row.', 'vessera' ) }
							</Notice>
						) }

						<table className="widefat striped vessera-import-mapping">
							<thead>
								<tr>
									<th>{ __( 'Course field', 'vessera' ) }</th>
									<th>{ __( 'Spreadsheet column', 'vessera' ) }</th>
									<th>{ __( 'First course row', 'vessera' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ FIELDS.map( ( field ) => (
									<tr key={ field.key }>
										<th scope="row">
											{ field.label }
											{ field.required && <span className="vessera-import-required"> { __( '(required)', 'vessera' ) }</span> }
											{ field.help && <p className="description">{ field.help }</p> }
										</th>
										<td>
											<SelectControl
												__nextHasNoMarginBottom
												__next40pxDefaultSize
												hideLabelFromVision
												label={ field.label }
												value={ field.key in mapping ? String( mapping[ field.key ] ) : '' }
												options={ columnOptions }
												onChange={ ( value ) => {
													const next = { ...mapping };
													if ( value === '' ) {
														delete next[ field.key ];
													} else {
														next[ field.key ] = parseInt( value, 10 );
													}
													setMapping( next );
												} }
											/>
										</td>
										<td className="vessera-import-sample">
											{ sample && field.key in mapping ? sample.cells[ mapping[ field.key ] ] : '' }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>

						{ Object.keys( prefixCounts ).length > 0 && (
							<section className="vessera-import-prefixes">
								<h3>{ __( 'Departments by course prefix', 'vessera' ) }</h3>
								<p className="description">
									{ 'department' in mapping
										? __( 'Used for rows whose department cell is empty.', 'vessera' )
										: __( 'Courses are placed in a department by the letters their code starts with.', 'vessera' ) }{ ' ' }
									{ __( 'These choices are saved to each department, so later imports and courses added by hand are filed the same way. Existing courses that already have a department keep it.', 'vessera' ) }
								</p>
								<table className="widefat striped vessera-import-prefix-table">
									<thead>
										<tr>
											<th>{ __( 'Prefix', 'vessera' ) }</th>
											<th>{ __( 'Courses', 'vessera' ) }</th>
											<th>{ __( 'Department', 'vessera' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ Object.entries( prefixCounts )
											.sort( ( [ a ], [ b ] ) => a.localeCompare( b ) )
											.map( ( [ prefix, count ] ) => (
												<tr key={ prefix }>
													<th scope="row">{ prefix }</th>
													<td>{ count }</td>
													<td>
														<SelectControl
															__nextHasNoMarginBottom
															__next40pxDefaultSize
															hideLabelFromVision
															label={ sprintf(
																/* translators: %s: course prefix */
																__( 'Department for %s courses', 'vessera' ),
																prefix
															) }
															value={ String( prefixDepartmentIds[ prefix ] || 0 ) }
															options={ [
																{ label: __( '— None —', 'vessera' ), value: '0' },
																...departments.map( ( d ) => ( { label: d.name, value: String( d.id ) } ) ),
															] }
															onChange={ ( value ) =>
																setPrefixChoices( { ...prefixChoices, [ prefix ]: parseInt( value, 10 ) } )
															}
														/>
													</td>
												</tr>
											) ) }
									</tbody>
								</table>
								<HStack justify="flex-start" alignment="bottom" className="vessera-import-add-department">
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __( 'Add a department', 'vessera' ) }
										value={ newDepartment }
										onChange={ setNewDepartment }
									/>
									<Button variant="secondary" disabled={ ! newDepartment.trim() || busy } onClick={ addDepartment }>
										{ __( 'Add', 'vessera' ) }
									</Button>
								</HStack>
							</section>
						) }

						<RadioControl
							label={ __( 'New courses', 'vessera' ) }
							selected={ options.status }
							options={ [
								{ label: __( 'Save as drafts for review', 'vessera' ), value: 'draft' },
								...( canPublish ? [ { label: __( 'Publish immediately', 'vessera' ), value: 'publish' } ] : [] ),
							] }
							onChange={ ( status ) => setOptions( { ...options, status } ) }
						/>

						<RadioControl
							label={ __( 'Courses that already exist (same course code)', 'vessera' ) }
							selected={ options.existing }
							options={ [
								{ label: __( 'Update them with the spreadsheet’s values', 'vessera' ), value: 'update' },
								{ label: __( 'Leave them unchanged', 'vessera' ), value: 'skip' },
							] }
							onChange={ ( value ) => setOptions( { ...options, existing: value } ) }
						/>

						<HStack justify="flex-start">
							<Button variant="secondary" onClick={ startOver }>
								{ __( 'Choose a different file', 'vessera' ) }
							</Button>
							<Button variant="primary" disabled={ ! ready || busy } isBusy={ busy } onClick={ toPreview }>
								{ __( 'Preview import', 'vessera' ) }
							</Button>
						</HStack>
						{ ! ready && (
							<p className="description">
								{ __( 'Choose columns for the course code and title, and a header row with courses below it, to continue.', 'vessera' ) }
							</p>
						) }
					</VStack>
				</CardBody>
			</Card>
		);
	}

	if ( step === 'preview' ) {
		const toImport = ( counts.create || 0 ) + ( counts.update || 0 );
		const unresolved = counts.duplicate || 0;
		const unresolvedGroups = groups.filter( ( group ) =>
			group.items.some( ( item ) => item.action === 'duplicate' )
		).length;

		const chooseAll = ( pick ) =>
			setChoices( {
				...choices,
				...Object.fromEntries(
					groups.map( ( group ) => [ group.key, pick( group.items ).rowNumber ] )
				),
			} );

		return (
			<Card className="vessera-import">
				<CardHeader>
					<h2>{ __( '3. Review before importing', 'vessera' ) }</h2>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 4 }>
						<ul className="vessera-import-counts">
							{ [ 'create', 'update', 'skip', 'duplicate', 'error' ]
								.filter( ( action ) => action !== 'duplicate' || counts.duplicate )
								.map( ( action ) => (
								<li key={ action } className={ `is-${ action }` }>
									<strong>{ counts[ action ] || 0 }</strong> { ACTION_LABELS[ action ] }
								</li>
							) ) }
						</ul>

						{ groups.length > 0 && (
							<section className="vessera-import-duplicates">
								<h3>
									{ sprintf(
										/* translators: %d: number of course codes */
										_n( '%d course code appears on more than one row', '%d course codes appear on more than one row', groups.length, 'vessera' ),
										groups.length
									) }
								</h3>
								<p>
									{ __( 'Choose which row to import for each code, or import none of them.', 'vessera' ) }
								</p>
								{ groups.length > 1 && (
									<HStack justify="flex-start" wrap>
										<Button variant="secondary" size="compact" onClick={ () => chooseAll( ( items ) => items[ 0 ] ) }>
											{ __( 'Use the first row for all', 'vessera' ) }
										</Button>
										<Button variant="secondary" size="compact" onClick={ () => chooseAll( ( items ) => items[ items.length - 1 ] ) }>
											{ __( 'Use the last row for all', 'vessera' ) }
										</Button>
									</HStack>
								) }
								<div className="vessera-import-duplicate-list">
									{ groups.map( ( group ) => {
										const choice = choices[ group.key ];
										return (
											<RadioControl
												key={ group.key }
												label={ group.code }
												selected={ choice === undefined ? '' : String( choice ) }
												options={ [
													...group.items.map( ( item ) => ( {
														value: String( item.rowNumber ),
														label: sprintf(
															/* translators: 1: row number, 2: course title, 3: credits */
															__( 'Row %1$d: %2$s%3$s', 'vessera' ),
															item.rowNumber,
															item.title,
															item.credits !== undefined
																? sprintf(
																		/* translators: %s: credit hours */
																		__( ' (%s credits)', 'vessera' ),
																		item.credits
																  )
																: ''
														),
													} ) ),
													{ value: '0', label: __( 'Don’t import this course', 'vessera' ) },
												] }
												onChange={ ( value ) =>
													setChoices( { ...choices, [ group.key ]: parseInt( value, 10 ) } )
												}
											/>
										);
									} ) }
								</div>
							</section>
						) }

						<div className="vessera-import-scroll">
							<table className="widefat striped vessera-import-preview">
								<thead>
									<tr>
										<th>{ __( 'Row', 'vessera' ) }</th>
										<th>{ __( 'Action', 'vessera' ) }</th>
										<th>{ __( 'Code', 'vessera' ) }</th>
										<th>{ __( 'Title', 'vessera' ) }</th>
										<th>{ __( 'Credits', 'vessera' ) }</th>
										<th>{ __( 'Department', 'vessera' ) }</th>
										<th>{ __( 'Prerequisites', 'vessera' ) }</th>
										<th>{ __( 'Notes', 'vessera' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ plan.map( ( item ) => (
										<tr key={ item.rowNumber }>
											<td>{ item.rowNumber }</td>
											<td>
												<span className={ `vessera-import-badge is-${ item.action }` }>
													{ ACTION_LABELS[ item.action ] }
												</span>
											</td>
											<td>{ item.code }</td>
											<td>{ item.title }</td>
											<td>{ item.credits ?? '' }</td>
											<td>
												{ item.department }
												{ item.departmentFromPrefix && (
													<div className="description">{ __( 'from prefix', 'vessera' ) }</div>
												) }
											</td>
											<td>
												{ item.prereqs?.codes?.join( ', ' ) }
												{ item.prereqs?.notes && (
													<div className="description">{ item.prereqs.notes }</div>
												) }
											</td>
											<td>{ item.messages.join( ' ' ) }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>

						<HStack justify="flex-start">
							<Button variant="secondary" onClick={ () => setStep( 'map' ) }>
								{ __( 'Back', 'vessera' ) }
							</Button>
							<Button variant="primary" disabled={ ! toImport || unresolved > 0 } onClick={ runImport }>
								{ sprintf(
									/* translators: %d: number of courses */
									_n( 'Import %d course', 'Import %d courses', toImport, 'vessera' ),
									toImport
								) }
							</Button>
						</HStack>
						{ unresolved > 0 && (
							<p className="description">
								{ sprintf(
									/* translators: %d: number of course codes */
									_n( 'Choose a row for %d duplicate code to continue.', 'Choose a row for each of the %d duplicate codes to continue.', unresolvedGroups, 'vessera' ),
									unresolvedGroups
								) }
							</p>
						) }
					</VStack>
				</CardBody>
			</Card>
		);
	}

	if ( step === 'importing' ) {
		return (
			<Card className="vessera-import">
				<CardHeader>
					<h2>{ __( 'Importing…', 'vessera' ) }</h2>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 3 }>
						<progress
							className="vessera-import-progress"
							max={ progress.total || 1 }
							value={ progress.done }
							aria-label={ __( 'Import progress', 'vessera' ) }
						/>
						<p>{ __( 'Please keep this page open until the import finishes.', 'vessera' ) }</p>
					</VStack>
				</CardBody>
			</Card>
		);
	}

	// step === 'done'
	return (
		<Card className="vessera-import">
			<CardHeader>
				<h2>{ __( 'Import complete', 'vessera' ) }</h2>
			</CardHeader>
			<CardBody>
				<VStack spacing={ 4 }>
					<ul className="vessera-import-counts" role="status">
						<li className="is-create"><strong>{ results.created }</strong> { __( 'Created', 'vessera' ) }</li>
						<li className="is-update"><strong>{ results.updated }</strong> { __( 'Updated', 'vessera' ) }</li>
						<li className="is-skip"><strong>{ results.skipped }</strong> { __( 'Skipped', 'vessera' ) }</li>
						<li className="is-error"><strong>{ results.errors.length }</strong>{ ' ' }
							{ _n( 'Error', 'Errors', results.errors.length, 'vessera' ) }</li>
					</ul>

					{ results.unresolved.length > 0 && (
						<>
							<h3>{ __( 'Prerequisites that didn’t match a course', 'vessera' ) }</h3>
							<p>
								{ __(
									'These codes were added to each course’s prerequisite notes instead. Import the missing courses and run the import again to link them.',
									'vessera'
								) }
							</p>
							<table className="widefat striped">
								<tbody>
									{ results.unresolved.map( ( item ) => (
										<tr key={ item.row }>
											<td>{ item.code }</td>
											<td>{ item.codes.join( ', ' ) }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</>
					) }

					{ results.errors.length > 0 && (
						<>
							<h3>{ __( 'Rows that weren’t imported', 'vessera' ) }</h3>
							<table className="widefat striped">
								<tbody>
									{ results.errors.map( ( item, i ) => (
										<tr key={ i }>
											<td>
												{ sprintf(
													/* translators: %d: spreadsheet row number */
													__( 'Row %d', 'vessera' ),
													item.row
												) }
											</td>
											<td>{ item.message }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</>
					) }

					<HStack justify="flex-start">
						<Button variant="primary" href={ coursesUrl }>
							{ __( 'View courses', 'vessera' ) }
						</Button>
						<Button variant="secondary" onClick={ startOver }>
							{ __( 'Import another file', 'vessera' ) }
						</Button>
					</HStack>
				</VStack>
			</CardBody>
		</Card>
	);
}
