import { __ } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';

import useMeta from '../hooks/use-meta';
import CreditsControl from './credits-control';
import PrerequisitePicker from './prerequisite-picker';

export default function CourseFields() {
	const [ meta, update ] = useMeta();

	return (
		<VStack spacing={ 4 }>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Course code', 'vessera' ) }
				help={ __( 'For example, CPD 153', 'vessera' ) }
				value={ meta._vessera_course_code ?? '' }
				onChange={ ( v ) => update( '_vessera_course_code', v ) }
			/>

			<CreditsControl
				label={ __( 'Credit hours', 'vessera' ) }
				value={ meta._vessera_credits }
				onChange={ ( v ) => update( '_vessera_credits', v ) }
			/>

			<details className="vessera-hours">
				<summary>{ __( 'Lecture, lab and contact hours (optional)', 'vessera' ) }</summary>
				<VStack spacing={ 3 }>
					<CreditsControl
						label={ __( 'Lecture hours', 'vessera' ) }
						value={ meta._vessera_lecture_hours }
						onChange={ ( v ) => update( '_vessera_lecture_hours', v ) }
					/>
					<CreditsControl
						label={ __( 'Lab hours', 'vessera' ) }
						value={ meta._vessera_lab_hours }
						onChange={ ( v ) => update( '_vessera_lab_hours', v ) }
					/>
					<CreditsControl
						label={ __( 'Contact hours', 'vessera' ) }
						value={ meta._vessera_contact_hours }
						onChange={ ( v ) => update( '_vessera_contact_hours', v ) }
					/>
				</VStack>
			</details>

			<PrerequisitePicker
				value={ meta._vessera_prerequisites ?? [] }
				onChange={ ( ids ) => update( '_vessera_prerequisites', ids ) }
			/>

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Course description', 'vessera' ) }
				help={ __( 'Shown by the Course Description block. Leave a blank line between paragraphs.', 'vessera' ) }
				rows={ 8 }
				value={ meta._vessera_description ?? '' }
				onChange={ ( v ) => update( '_vessera_description', v ) }
			/>

			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Prerequisite notes', 'vessera' ) }
				help={ __(
					'Anything the list above can’t express, such as “or instructor permission.”',
					'vessera'
				) }
				value={ meta._vessera_prerequisite_notes ?? '' }
				onChange={ ( v ) => update( '_vessera_prerequisite_notes', v ) }
			/>
		</VStack>
	);
}
