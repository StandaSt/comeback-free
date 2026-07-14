import { Parent, ResolveProperty, Resolver } from '@nestjs/graphql';

import Branch from '../branch/branch.entity';

import PreferredHour from './preferredHour.entity';

@Resolver(() => PreferredHour)
class PreferredHourResolver {
  @ResolveProperty(() => Boolean)
  async notAssigned(@Parent() parent: PreferredHour) {
    return (await parent.dbShiftHour) === undefined;
  }

  @ResolveProperty(() => Branch, { nullable: true })
  async assignedToBranch(@Parent() parent: PreferredHour) {
    const shiftHour = await parent.dbShiftHour;
    if (!shiftHour) return null;

    return (await (await (await shiftHour?.shiftRole).shiftDay).shiftWeek)
      .branch;
  }
}

export default PreferredHourResolver;
