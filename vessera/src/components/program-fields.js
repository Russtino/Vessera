import { __ } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';

import useMeta from '../hooks/use-meta';

/**
 * Program Details panel. Every field is optional; empty ones aren't shown on the page.
 */
export default function ProgramFields() {
	const [ meta, update ] = useMeta();

	const text = ( key, label, help ) => (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			help={ help }
			value={ meta[ key ] ?? '' }
			onChange={ ( v ) => update( key, v ) }
		/>
	);

	return (
		<VStack spacing={ 4 }>
			{ text(
				'_vessera_start_terms',
				__( 'Start dates', 'vessera' ),
				__( 'For example, August (fall semester)', 'vessera' )
			) }

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Enrollment notice', 'vessera' ) }
				help={ __(
					'Shown prominently on the program page and in the Program Finder, for example “Not accepting applications for Fall 2026. Next start: Fall 2027.” Leave empty to show nothing.',
					'vessera'
				) }
				rows={ 3 }
				value={ meta._vessera_enrollment_notice ?? '' }
				onChange={ ( v ) => update( '_vessera_enrollment_notice', v ) }
			/>

			{ text(
				'_vessera_cip_code',
				__( 'CIP code', 'vessera' ),
				__( 'Federal Classification of Instructional Programs code, for example 11.1006', 'vessera' )
			) }
			{ text(
				'_vessera_cip_title',
				__( 'CIP title', 'vessera' ),
				__( 'For example, Computer Support Specialist', 'vessera' )
			) }
		</VStack>
	);
}
