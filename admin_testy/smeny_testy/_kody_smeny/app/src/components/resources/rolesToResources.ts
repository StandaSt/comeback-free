interface Role {
  resources: {
    name: string;
  }[];
}
const rolesToResources = (roles: Role[]) => {
  const resources = [];
  roles.forEach(role => {
    role.resources.forEach(resource => {
      resources.push(resource.name);
    });
  });

  return resources;
};

export default rolesToResources;
