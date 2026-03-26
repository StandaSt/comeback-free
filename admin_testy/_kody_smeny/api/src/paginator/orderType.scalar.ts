import { GraphQLScalarType, Kind } from 'graphql';

export type OrderType = 'ASC' | 'DESC';

const validateValue = (value: any) => value === 'ASC' || value === 'DESC';

const GraphqlOrderType = new GraphQLScalarType({
  name: 'OrderType',
  description: 'Accept ASC or DESC value on string format',
  parseValue(value: string) {
    // value from the client input variables
    if (validateValue(value)) return value;

    return undefined;
  },
  serialize(value: OrderType) {
    // value sent to the client
    return value;
  },
  parseLiteral(ast) {
    // value from the client query
    if (ast.kind === Kind.STRING) {
      if (validateValue(ast.value)) {
        return ast.value;
      }
    }

    return undefined;
  },
});

export default GraphqlOrderType;
