import { gql } from 'apollo-boost';

const RESOURCES_QUERY = gql`
  {
    resourceFindAll {
      id
      label
      description
      category {
        id
        label
      }
    }
  }
`;

export default RESOURCES_QUERY;
