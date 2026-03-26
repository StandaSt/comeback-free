const routes = {
  login: '/login',
  dashboard: '/dashboard',
  roles: {
    index: '/roles',
    resourceDetail: '/roles/resourceDetail',
    resourceCategoryDetail: '/roles/resourceCategoryDetail',
    roleDetail: '/roles/roleDetail',
    addRole: '/roles/addRole',
  },
  users: {
    index: '/users',
    userDetail: '/users/userDetail',
    addUser: '/users/addUser',
  },
  profile: {
    index: '/profile',
    changePassword: '/profile/changePassword',
    editProfile: '/profile/editProfile',
  },
  branches: {
    index: '/branches',
    detail: '/branches/detail',
    add: '/branches/addReceiver',
  },
  nextWeekPlanning: {
    index: '/nextWeekPlanning',
  },
  currentWeekPlanning: '/currentWeekPlanning',
  shiftRoleTypes: {
    index: '/shiftRoleTypes',
    add: '/shiftRoleTypes/add',
    edit: '/shiftRoleTypes/edit',
  },
  preferredWeeks: {
    index: '/preferredWeeks',
    week: '/preferredWeeks/week',
    overview: '/preferredWeeks/overview',
  },
  globalSettings: '/globalSettings',
  register: '/registration',
  currentWorkingWeek: '/currentWorkingWeek',
  nextWorkingWeek: '/nextWorkingWeek',
  shiftWeekTemplates: {
    index: '/shiftWeekTemplates',
    week: '/shiftWeekTemplates/week',
  },
  currentEnteredPreferredWeeks: '/currentEnteredPreferredWeeks',
  nextEnteredPreferredWeeks: '/nextEnteredPreferredWeeks',
  enteredPreferredWeeks: {
    history: {
      index: '/enteredPreferredWeeks/history',
      week: '/enteredPreferredWeeks/history/week',
    },
  },
  weekSummary: {
    history: {
      index: '/weekSummary/history',
      week: '/weekSummary/history/week',
    },
  },
  currentWeekSummary: '/currentWeekSummary',
  nextWeekSummary: '/nextWeekSummary',
  news: '/news',
  actionHistory: {
    index: '/actionHistory',
    detail: '/actionHistory/detail',
  },
  evaluation: {
    index: '/evaluation',
    detail: '/evaluation/detail',
  },
  notifications: {
    index: '/notifications',
    eventNotification: '/notifications/eventNotification',
    timeNotification: {
      index: '/notifications/timeNotification',
      addReceiver: '/notifications/timeNotification/addReceiver',
      editReceiver: '/notifications/timeNotification/editReceiver',
    },
  },
  myEvaluation: {
    index: '/myEvaluation',
  },
};

export default routes;
