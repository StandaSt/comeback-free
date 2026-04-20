import React from 'react';
import AssessmentIcon from '@material-ui/icons/Assessment';
import AssignmentIcon from '@material-ui/icons/Assignment';
import AssignmentIndIcon from '@material-ui/icons/AssignmentInd';
import BuildIcon from '@material-ui/icons/Build';
import DashboardIcon from '@material-ui/icons/Dashboard';
import DateRangeIcon from '@material-ui/icons/DateRange';
import DescriptionIcon from '@material-ui/icons/Description';
import FastForwardIcon from '@material-ui/icons/FastForward';
import GroupIcon from '@material-ui/icons/Group';
import HistoryIcon from '@material-ui/icons/History';
import ListIcon from '@material-ui/icons/List';
import LockIcon from '@material-ui/icons/Lock';
import PlayArrowIcon from '@material-ui/icons/PlayArrow';
import PlayForWork from '@material-ui/icons/PlayForWork';
import SettingsIcon from '@material-ui/icons/Settings';
import StoreFrontIcon from '@material-ui/icons/Storefront';
import routes from '@shift-planner/shared/config/app/routes';
import StarRateIcon from '@material-ui/icons/StarRate';
import Notifications from '@material-ui/icons/Notifications';

import actionHistoryResources from 'pages/actionHistory/resources';
import branchesResources from 'pages/branches/index/resources';
import enteredPreferredWeeksResources from 'pages/enteredPreferredWeeks/resources';
import globalSettingsResources from 'pages/globalSettings/resources';
import newsResources from 'pages/news/resources';
import preferredWeeksResources from 'pages/preferredWeeks/index/resources';
import rolesResources from 'pages/roles/index/resources';
import shiftRoleTypesResources from 'pages/shiftRoleTypes/index/resources';
import shiftWeekTemplatesResources from 'pages/shiftWeekTemplates/index/resources';
import usersResources from 'pages/users/index/resources';
import planningResources from 'pages/weekPlanning/shared/resources';
import currentWeekSummaryResources from 'pages/weekSummary/currentWeekSummary/resources';
import nextWeekSummaryResources from 'pages/weekSummary/nextWeekSummary/resources';
import workingWeekResources from 'pages/workingWeek/shared/resources';

import evaluationResources from '../../../pages/evaluation/index/resources';
import notificationsResources from '../../../pages/notifications/index/resources';

export interface ListConfig {
  label: string;
  icon: JSX.Element;
  link?: string;
  subList?: ListConfig[];
  resources?: string[];
}

const listConfig: ListConfig[] = [
  { label: 'Přehled', icon: <DashboardIcon />, link: routes.dashboard },
  {
    label: 'Požadavky',
    icon: <AssignmentIcon />,
    link: routes.preferredWeeks.index,
    resources: preferredWeeksResources,
  },
  {
    label: 'Novinky',
    icon: <DescriptionIcon />,
    link: routes.news,
    resources: newsResources,
  },
  {
    label: 'Hodnocení',
    icon: <StarRateIcon />,
    link: routes.evaluation.index,
    resources: evaluationResources,
  },
  {
    label: 'Mé směny',
    icon: <DateRangeIcon />,
    resources: workingWeekResources,
    subList: [
      {
        label: 'Aktuální týden',
        icon: <PlayArrowIcon />,
        link: routes.currentWorkingWeek,
        resources: workingWeekResources,
      },
      {
        label: 'Následující týden',
        icon: <FastForwardIcon />,
        link: routes.nextWorkingWeek,
        resources: workingWeekResources,
      },
    ],
  },
  {
    label: 'Plánování směn',
    icon: <AssignmentIcon />,
    resources: [...planningResources, ...shiftWeekTemplatesResources],
    subList: [
      {
        label: 'Aktuální týden',
        icon: <PlayArrowIcon />,
        link: routes.currentWeekPlanning,
        resources: planningResources,
      },
      {
        label: 'Následující týden',
        icon: <FastForwardIcon />,
        link: routes.nextWeekPlanning.index,
        resources: planningResources,
      },
      {
        label: 'Šablony',
        icon: <ListIcon />,
        link: routes.shiftWeekTemplates.index,
        resources: shiftWeekTemplatesResources,
      },
    ],
  },
  {
    label: 'Naplánované směny',
    icon: <PlayForWork />,
    resources: [
      ...nextWeekSummaryResources,
      ...currentWeekSummaryResources,
      ...nextWeekSummaryResources,
    ],
    subList: [
      {
        label: 'Aktuální týden ',
        icon: <PlayArrowIcon />,
        link: routes.currentWeekSummary,
        resources: currentWeekSummaryResources,
      },
      {
        label: 'Následující týden ',
        icon: <FastForwardIcon />,
        link: routes.nextWeekSummary,
        resources: nextWeekSummaryResources,
      },
      {
        label: 'Historie',
        icon: <HistoryIcon />,
        link: routes.weekSummary.history.index,
        resources: nextWeekSummaryResources,
      },
    ],
  },
  {
    label: 'Zadané požadavky',
    icon: <AssessmentIcon />,
    resources: [...enteredPreferredWeeksResources],
    subList: [
      {
        label: 'Aktuální týden',
        icon: <PlayArrowIcon />,
        link: routes.currentEnteredPreferredWeeks,
        resources: enteredPreferredWeeksResources,
      },
      {
        label: 'Následující týden',
        icon: <FastForwardIcon />,
        link: routes.nextEnteredPreferredWeeks,
        resources: enteredPreferredWeeksResources,
      },
      {
        label: 'Historie',
        icon: <HistoryIcon />,
        link: routes.enteredPreferredWeeks.history.index,
        resources: enteredPreferredWeeksResources,
      },
    ],
  },
  {
    label: 'Administrace',
    icon: <BuildIcon />,
    resources: [
      ...usersResources,
      ...rolesResources,
      ...branchesResources,
      ...shiftRoleTypesResources,
      ...globalSettingsResources,
      ...actionHistoryResources,
    ],
    subList: [
      {
        label: 'Uživatelé',
        icon: <GroupIcon />,
        link: routes.users.index,
        resources: usersResources,
      },
      {
        label: 'Role',
        icon: <LockIcon />,
        link: routes.roles.index,
        resources: rolesResources,
      },
      {
        label: 'Pobočky',
        icon: <StoreFrontIcon />,
        link: routes.branches.index,
        resources: branchesResources,
      },
      {
        label: 'Typy slotů',
        icon: <AssignmentIndIcon />,
        link: routes.shiftRoleTypes.index,
        resources: shiftRoleTypesResources,
      },
      {
        label: 'Globální nastavení',
        icon: <SettingsIcon />,
        link: routes.globalSettings,
        resources: globalSettingsResources,
      },
      {
        label: 'Logování',
        icon: <HistoryIcon />,
        link: routes.actionHistory.index,
        resources: actionHistoryResources,
      },
      {
        label: 'Notifikace',
        icon: <Notifications />,
        link: routes.notifications.index,
        resources: notificationsResources,
      },
    ],
  },
];

export default listConfig;
