import { Box } from '@material-ui/core';
import React from 'react';

const OverlayLoadingContainer: React.FC = ({ children }) => (
  <Box position="relative">{children}</Box>
);

export default OverlayLoadingContainer;
