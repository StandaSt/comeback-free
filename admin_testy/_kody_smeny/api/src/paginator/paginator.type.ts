import { ClassType, Field, Int, ObjectType } from 'type-graphql';

// Any needs to be here or throwing ts error "TS4060: Return type of exported function has or is using private name"
function Paginator<TNodeType>(NodeType: ClassType<TNodeType>): any {
  @ObjectType({ isAbstract: true })
  abstract class PaginatorType {
    @Field(() => NodeType)
    items: TNodeType[];

    @Field(() => Int)
    totalCount: number;
  }

  return PaginatorType;
}

export default Paginator;
