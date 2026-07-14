import React from 'react';

export interface Breadcrumb {
  label: string;
  link?: string;
}

export interface User {
  id: number;
  name: string;
  surname: string;
  darkTheme: boolean;
  totalEvaluationScore: number;
}

export interface UserGetLogged {
  userGetLogged: User;
}

export interface PageProps {
  Component: React.ElementType;
  breadcrumbs: Breadcrumb[];
  user: User;
}
