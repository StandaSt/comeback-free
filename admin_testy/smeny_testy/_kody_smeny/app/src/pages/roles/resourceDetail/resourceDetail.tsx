import { IconButton, Typography } from '@material-ui/core';
import InfoIcon from '@material-ui/icons/Info';
import Link from 'next/link';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';

import { ResourceDetailProps } from './types';

const ResourceDetail: React.FC<ResourceDetailProps> = props => {
  const { resource } = props;

  const description = resource ? resource.description : '';
  const category = resource ? resource.category : { id: undefined, label: '' };
  const roles = resource ? resource.roles : [];
  const minimalCount = resource ? resource.minimalCount : '';
  const requires = resource ? resource.requires : [];
  const requiredBy = resource ? resource.requiredBy : [];

  return (
    <>
      <Typography>{description}</Typography>
      <SimpleTable>
        <>
          <SimpleRow
            name="Kategorie"
            tooltip="Kategorie pod kterou daný zdroj spadá"
          >
            <Link
              href={{
                pathname: routes.roles.resourceCategoryDetail,
                query: { categoryId: category.id },
              }}
            >
              <IconButton color="primary">
                <InfoIcon />
              </IconButton>
            </Link>
            {category.label}
          </SimpleRow>
          <SimpleRow name="Role" tooltip="Role které mají daný zdroj aktivní">
            {roles.map(role => (
              <div key={`role${role.id}`}>
                <Link
                  href={{
                    pathname: routes.roles.roleDetail,
                    query: { roleId: role.id },
                  }}
                >
                  <IconButton color="primary">
                    <InfoIcon />
                  </IconButton>
                </Link>
                {role.name}
              </div>
            ))}
          </SimpleRow>
          <SimpleRow
            name="Minimální počet"
            tooltip="Minimánlí počet rolí, které musí obsahovat tento zdroj"
          >
            {minimalCount}
          </SimpleRow>
          <SimpleRow
            name="Vyžaduje"
            tooltip="Pravomoce které musí mít role aktivní, aby bylo možné aktivovat tento zdroj"
          >
            {requires.map(r => (
              <div key={`requires${r.id}`}>
                <Link
                  href={{
                    pathname: routes.roles.resourceDetail,
                    query: { resourceId: r.id },
                  }}
                >
                  <IconButton color="primary">
                    <InfoIcon />
                  </IconButton>
                </Link>
                {r.label}
              </div>
            ))}
          </SimpleRow>
          <SimpleRow
            name="Je vyžadována v"
            tooltip="Pravomoce, které potřebují tento zdroj aktivní, aby mohli být sami aktivní"
          >
            {requiredBy.map(r => (
              <div key={`requiredBy${r.id}`}>
                <Link
                  href={{
                    pathname: routes.roles.resourceDetail,
                    query: { resourceId: r.id },
                  }}
                >
                  <IconButton color="primary">
                    <InfoIcon />
                  </IconButton>
                </Link>
                {r.label}
              </div>
            ))}
          </SimpleRow>
        </>
      </SimpleTable>
    </>
  );
};

export default ResourceDetail;
