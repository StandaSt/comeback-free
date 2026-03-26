import { useMutation } from '@apollo/react-hooks';
import {
  FormControl,
  InputLabel,
  makeStyles,
  MenuItem,
  Select,
  Theme,
} from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import React from 'react';
import resources from '@shift-planner/shared/config/api/resources';

import mainBranchFragment from 'pages/users/fragments/mainBranchFragment';
import useResources from 'components/resources/useResources';

import {
  MainBranchProps,
  UserChangeMainBranch,
  UserChangeMainBranchVars,
} from './types';

const useStyles = makeStyles((theme: Theme) => ({
  select: {
    minWidth: theme.spacing(20),
  },
}));

const USER_CHANGE_MAIN_BRANCH = gql`
  ${mainBranchFragment}
  mutation($userId: Int!, $branchId: Int!) {
    userChangeMainBranch(userId: $userId, branchId: $branchId) {
      id
      ...MainBranch
    }
  }
`;

const MainBranch: React.FC<MainBranchProps> = props => {
  const classes = useStyles();

  const canEdit = useResources([resources.users.edit]);

  const [userChangeMainBranch, { loading }] = useMutation<
    UserChangeMainBranch,
    UserChangeMainBranchVars
  >(USER_CHANGE_MAIN_BRANCH);
  const { enqueueSnackbar } = useSnackbar();

  const mappedItems = props.branches?.map(b => (
    <MenuItem key={b.id} value={b.id}>
      {b.name}
    </MenuItem>
  ));

  const changeHandler = (id: number): void => {
    userChangeMainBranch({ variables: { userId: props.userId, branchId: id } })
      .then(() => {
        enqueueSnackbar('Hlavní pobočka úspěšně změněna', {
          variant: 'success',
        });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se změnit hlavní pobočku', {
          variant: 'error',
        });
      });
  };

  return (
    <FormControl disabled={loading || !canEdit}>
      <InputLabel id="mainBranchLabel">Hlavní pobočka</InputLabel>
      <Select
        labelId="mainBranchLabel"
        className={classes.select}
        value={props.mainBranch?.id || ''}
        onChange={e => changeHandler(+e.target.value)}
      >
        {mappedItems}
      </Select>
    </FormControl>
  );
};

export default MainBranch;
