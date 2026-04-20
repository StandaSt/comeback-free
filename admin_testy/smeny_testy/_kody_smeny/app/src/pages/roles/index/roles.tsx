import {
  Checkbox,
  IconButton,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Theme,
  Tooltip,
} from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import AddIcon from '@material-ui/icons/AddCircle';
import InfoIcon from '@material-ui/icons/Info';
import WarningIcon from '@material-ui/icons/Warning';
import Link from 'next/link';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import useResources from 'components/resources/useResources';

import { RolesProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  highlight: {
    transitionDuration: '1s',
    backgroundColor: theme.palette.primary.main,
  },
  resource: {
    transitionDelay: '1s',
    transitionDuration: '1s',
  },
}));

const Roles: React.FC<RolesProps> = props => {
  const classes = useStyles();
  const router = useRouter();
  const canEditResources = useResources([resources.roles.editResources]);
  const canEditRoles = useResources([resources.roles.editRoles]);

  const highlightedResourceId = +router.query.resourceId;

  if (highlightedResourceId) {
    setTimeout(() => {
      router.push(routes.roles.index);
    }, 1000);
  }

  const DetailTooltip: React.FC = (p: { children: JSX.Element }) => (
    <Tooltip title="Detail" arrow>
      {p.children}
    </Tooltip>
  );

  const mappedHead = props.roles.map(role => (
    <TableCell key={`head${role.id}`} padding="none">
      <Link
        href={{ pathname: routes.roles.roleDetail, query: { roleId: role.id } }}
        passHref
      >
        {/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
        <a>
          <DetailTooltip>
            <IconButton color="primary">
              <InfoIcon />
            </IconButton>
          </DetailTooltip>
        </a>
      </Link>
      {role.name}
    </TableCell>
  ));

  const mappedBody = props.resourceCategories.map(category => {
    const categoryResources = category.resources.map(resource => {
      const resourceRoles = [...props.roles, { id: -1, name: '' }].map(role => {
        const changed = props.changedResources.some(
          ch => ch.resourceId === resource.id && ch.roleId === role.id,
        );
        const active = resource.roles.some(r => r.id === role.id);
        const changedActive = changed ? !active : active;

        const checkboxChangeHandler = (): void => {
          props.onResourceChange(resource.id, role.id, !changedActive);
        };

        let disabled = false;
        resource.requires.forEach(req => {
          const changedRequested = props.changedResources.find(
            res => res.resourceId === req.id && res.roleId === role.id,
          );
          if (changedRequested) {
            if (!changedRequested.active) disabled = true;
            if (disabled && changedActive) {
              props.onResourceChange(resource.id, role.id, false);
            }
          } else if (
            !props.resourceCategories.some(cat =>
              cat.resources.some(
                res =>
                  res.id === req.id && res.roles.some(r => r.id === role.id),
              ),
            )
          ) {
            disabled = true;
            if (changedActive)
              props.onResourceChange(resource.id, role.id, !changedActive);
          }
        });

        return (
          <TableCell
            key={`resourceRole${role.id}-${category.id}`}
            padding="none"
          >
            <Checkbox
              checked={changedActive}
              disabled={role.id < 0 || disabled || !canEditResources}
              onChange={checkboxChangeHandler}
            />
          </TableCell>
        );
      });
      let rolesCount = resource.roles.length;
      props.changedResources.forEach(changed => {
        if (changed.resourceId === resource.id) {
          if (changed.active) rolesCount++;
          else rolesCount--;
        }
      });
      const minimalCountError = resource.minimalCount > rolesCount;

      return (
        <React.Fragment key={`categoryResource${category.id}-${resource.id}`}>
          <TableRow>
            <TableCell
              id={`resource-${resource.id}`}
              padding="none"
              className={
                resource.id === highlightedResourceId
                  ? classes.highlight
                  : classes.resource
              }
            >
              <Link
                href={{
                  pathname: routes.roles.resourceDetail,
                  query: { resourceId: resource.id },
                }}
              >
                {/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
                <a>
                  <DetailTooltip>
                    <IconButton color="primary">
                      <InfoIcon />
                    </IconButton>
                  </DetailTooltip>
                </a>
              </Link>
              {resource.label}
              {minimalCountError && (
                <IconButton
                  color="secondary"
                  onClick={() => {
                    props.enqueueSnackbar(
                      'Nesplněn minimální počet pravomoce',
                      {
                        variant: 'warning',
                      },
                    );
                  }}
                >
                  <WarningIcon />
                </IconButton>
              )}
            </TableCell>
            {resourceRoles}
          </TableRow>
        </React.Fragment>
      );
    });
    const emptyCells = props.roles.map(role => (
      <TableCell key={`categoryEmpty${role.id}-${role.id}`} />
    ));

    return (
      <React.Fragment key={`category${category.id}`}>
        <TableRow>
          <TableCell padding="none">
            <Link
              href={{
                pathname: routes.roles.resourceCategoryDetail,
                query: { categoryId: category.id },
              }}
            >
              {/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
              <a>
                <DetailTooltip>
                  <IconButton color="primary">
                    <InfoIcon />
                  </IconButton>
                </DetailTooltip>
              </a>
            </Link>
            <b>{category.label}</b>
          </TableCell>
          {emptyCells}
          <TableCell />
        </TableRow>
        {categoryResources}
      </React.Fragment>
    );
  });

  return (
    <>
      <Table>
        <TableHead>
          <TableRow>
            <TableCell>Pravomoce</TableCell>
            {mappedHead}
            <TableCell padding="none">
              <Link href={routes.roles.addRole}>
                {/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
                <a>
                  <Tooltip title="Přidat roli" arrow>
                    <IconButton color="primary" disabled={!canEditRoles}>
                      <AddIcon />
                    </IconButton>
                  </Tooltip>
                </a>
              </Link>
            </TableCell>
          </TableRow>
        </TableHead>
        <TableBody>{mappedBody}</TableBody>
      </Table>
    </>
  );
};

export default withSnackbar(Roles);
