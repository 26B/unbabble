import { useState } from 'react';

import { Flex, Button, SelectControl, Modal } from '@wordpress/components';

const Types = ( {
	title,
	types,
	setTypes,
	allTypes,
	addLabel,
	selectLabel,
	addSelectedLabel,
	readOnly,
} ) => {
	const [ showAddTypeModal, setShowAddTypeModal ] = useState( false );
	const [ showRemoveTypeModal, setShowRemoveTypeModal ] = useState( false );
	const [ typeToAdd, setTypeToAdd ] = useState( '' );
	const [ typeToRemove, setTypeToRemove ] = useState( '' );

	const removeType = () => {
		setTypes( types.filter( ( type ) => type !== typeToRemove ) );
		setTypeToRemove( '' );
	};

	const addType = () => {
		setTypes( types.concat( [ typeToAdd ] ) );
		setShowAddTypeModal( false );
	};

	const openAddTypeModal = () => {
		setShowAddTypeModal( true );
		setTypeToAdd( '' );
	};
	const closeAddTypeModal = () => {
		setShowAddTypeModal( false );
		setTypeToAdd( '' );
	};

	const openRemoveTypeModal = ( type ) => {
		setShowRemoveTypeModal( true );
		setTypeToRemove( type );
	};
	const closeRemoveTypeModal = () => {
		setShowRemoveTypeModal( false );
		setTypeToRemove( '' );
	};

	return (
		<>
			<h3>{ title }</h3>
			<Flex
				direction="row"
				style={ { width: '100%', justifyContent: 'normal' } }
			>
				<div
					style={ {
						display: 'grid',
						flexWrap: 'wrap',
						gap: '8px',
						maxHeight: '472px', // 15 elements before overflow.
						overflow: 'scroll',
						padding: '20px',
						background: 'white',
						border: '2px solid #ccc',
						borderRadius: '5px',
					} }
				>
					{ types.map( ( type ) => {
						return (
							<Flex
								direction="row"
								style={ {
									gap: 32,
								} }
								key={ type }
							>
								<span>{ type }</span>
								{ ! readOnly && (
									<Button
										variant="tertiary"
										onClick={ () =>
											openRemoveTypeModal( type )
										}
										isSmall
										isDestructive
									>
										Remove
									</Button>
								) }
							</Flex>
						);
					} ) }
					{ types.length === 0 && <span>None selected.</span> }
				</div>
				<div
					style={ {
						display: 'grid',
						gap: 8,
						alignSelf: 'baseline',
					} }
				>
					<Button
						variant="secondary"
						onClick={ openAddTypeModal }
						disabled={ readOnly }
					>
						{ addLabel }
					</Button>
				</div>
				{ showAddTypeModal && (
					<Modal
						title={ addLabel }
						onRequestClose={ closeAddTypeModal }
					>
						<SelectControl
							value={ typeToAdd }
							onChange={ ( selection ) => {
								setTypeToAdd( selection );
							} }
						>
							<option value="">{ selectLabel }</option>
							{ allTypes
								.filter( ( type ) => ! types.includes( type ) )
								.map( ( type ) => (
									<option value={ type } key={ type }>
										{ type }
									</option>
								) ) }
						</SelectControl>
						{ typeToAdd && (
							<Button variant="secondary" onClick={ addType }>
								{ addSelectedLabel }
							</Button>
						) }
					</Modal>
				) }
				{ showRemoveTypeModal && typeToRemove && (
					<Modal
						title="Removing Language"
						onRequestClose={ closeRemoveTypeModal }
					>
						<p>
							Are you sure you want to remove{ ' ' }
							<b>{ typeToRemove }</b>?
						</p>
						<Button
							style={ { marginRight: 10 } }
							variant="secondary"
							onClick={ removeType }
						>
							Remove
						</Button>
						<Button
							variant="secondary"
							onClick={ closeRemoveTypeModal }
						>
							Cancel
						</Button>
					</Modal>
				) }
			</Flex>
		</>
	);
};

export default Types;
