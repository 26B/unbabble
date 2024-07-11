import { Spinner } from '@wordpress/components';

const Loading = ({ overlay = false, size = 'md' }) => {
	const styles = {
		size: {
			sm: {
				width: '20px',
				height: '20px',
			},
			md: {
				width: '40px',
				height: '40px',
			},
			lg: {
				width: '60px',
				height: '60px',
			},
		},
		overlay: {
			alignItems: 'center',
			backgroundColor: 'rgba(255, 255, 255, 0.8)',
			bottom: 0,
			display: 'flex',
			height: '100%',
			justifyContent: 'center',
			left: 0,
			position: 'absolute',
			right: 0,
			top: 0,
			width: '100%',
			zIndex: 1000,
		},
	};

	return (
		<div style={{ ...(overlay ? styles.overlay : {}) }}>
			<Spinner style={{ ...styles.size[size] }} />
		</div>
	);
};

export default Loading;
