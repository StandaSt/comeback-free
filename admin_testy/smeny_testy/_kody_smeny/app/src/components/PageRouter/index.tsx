import { useRouter } from 'next/router';
import React from 'react';

import { PageRouterProps } from './types';

const PageRouter = (props: PageRouterProps) => {
  const router = useRouter();
  const defaultPage = props.pages.find(p => p.default) || props.pages[0];

  // eslint-disable-next-line no-underscore-dangle
  const renderPage =
    props.pages.find(p => p.name === router.query.__pageRouter__) ||
    defaultPage;
  const RenderPage = renderPage.component;

  return (
    <>
      {!renderPage.disabled && (
        <RenderPage
          {...renderPage.props}
          redirect={(name: string, q?: Record<string, string>) => {
            props.onPageChange(name);
            router.push({
              pathname: router.pathname,
              query: { ...router.query, __pageRouter__: name, ...q },
            });
          }}
          query={router.query}
        />
      )}
    </>
  );
};

export default PageRouter;
