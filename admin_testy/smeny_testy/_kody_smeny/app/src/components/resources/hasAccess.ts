const hasAccess = (userResources: string[], requiredResources: string[]) => {
  if (requiredResources.length === 0) return true;

  // eslint-disable-next-line consistent-return
  for (const resource of requiredResources) {
    if (userResources.some(userResource => userResource === resource))
      return true;
  }

  return false;
};

export default hasAccess;
