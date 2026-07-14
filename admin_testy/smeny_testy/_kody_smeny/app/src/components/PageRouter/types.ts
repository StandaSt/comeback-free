import React from 'react';

export interface RouterPageProps<Query = any> {
  redirect: (name: string, query?: any) => void;
  query: Query;
}

export interface PageRouterProps {
  pages: {
    name: string;
    component: React.ComponentType<RouterPageProps>;
    default?: boolean;
    props?: any;
    disabled?: boolean;
  }[];
  onPageChange: (page: string) => void;
}
