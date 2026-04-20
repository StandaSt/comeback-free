import {MigrationInterface, QueryRunner} from "typeorm";
import ShiftDay from "../shiftDay/shiftDay.entity";

export class AddRelationShiftHourPreferredHour1591547077354 implements MigrationInterface {
    name = 'AddRelationShiftHourPreferredHour1591547077354'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `preferred_hour` ADD `visible` tinyint NOT NULL DEFAULT 1", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD `preferredHourId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD UNIQUE INDEX `IDX_3142bfbe5ab5395d166b103264` (`preferredHourId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_3142bfbe5ab5395d166b103264` ON `shift_hour` (`preferredHourId`)", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD CONSTRAINT `FK_3142bfbe5ab5395d166b103264a` FOREIGN KEY (`preferredHourId`) REFERENCES `preferred_hour`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);

        const shiftHours: any = await queryRunner.query("SELECT shift_hour.id,shift_hour.startHour,shift_hour.dbWorkerId,shift_week.startDay,shift_day.day FROM shift_hour INNER JOIN shift_role on shift_hour.shiftRoleId = shift_role.id INNER JOIN shift_day on shift_role.shiftDayId = shift_day.id INNER JOIN shift_week on shift_day.shiftWeekId = shift_week.id");

        for (const shiftHour of shiftHours) {
            if (shiftHour.dbWorkerId) {
                const getPreferredHour = async () => {
                    const preferredHours = await queryRunner.query("SELECT preferred_hour.id, preferred_week.userId FROM preferred_hour INNER JOIN preferred_day ON preferred_hour.preferredDayId = preferred_day.id INNER JOIN preferred_week ON preferred_day.preferredWeekId = preferred_week.id WHERE preferred_hour.startHour = ? AND preferred_week.startDay = ? AND preferred_week.userId = ? AND preferred_day.day = ?", [shiftHour.startHour, shiftHour.startDay, shiftHour.dbWorkerId, shiftHour.day])

                    return preferredHours[0] || null
                }
                let preferredHour = await getPreferredHour()

                if (!preferredHour) {
                    //create new and set visible to false
                    const preferredDays = await queryRunner.query("SELECT preferred_day.id FROM preferred_week INNER JOIN preferred_day ON preferred_week.id = preferred_day.preferredWeekId WHERE preferred_week.startDay=? AND preferred_week.userId = ? AND preferred_day.day = ?", [shiftHour.startDay, shiftHour.dbWorkerId, shiftHour.day, shiftHour.day])
                    const preferredDay = preferredDays[0] || null;
                    if (preferredDay) {
                        await queryRunner.query("INSERT INTO preferred_hour (startHour,preferredDayId,visible) VALUES (?,?,?)", [shiftHour.startHour, preferredDay.id, false])
                        preferredHour = await getPreferredHour();
                    }
                }
                //addReceiver relation
                try {
                    await queryRunner.query("UPDATE shift_hour SET preferredHourId=? WHERE shift_hour.id = ?", [preferredHour.id, shiftHour.id])
                }catch (e) {
                    console.log(e)
                }
            }
        }
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("UPDATE shift_hour SET preferredHourId=?",[null])
        await queryRunner.query("DELETE FROM preferred_hour WHERE preferred_hour.visible = ?", [false])

        await queryRunner.query("ALTER TABLE `shift_hour` DROP FOREIGN KEY `FK_3142bfbe5ab5395d166b103264a`", undefined);
        await queryRunner.query("DROP INDEX `REL_3142bfbe5ab5395d166b103264` ON `shift_hour`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` DROP INDEX `IDX_3142bfbe5ab5395d166b103264`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` DROP COLUMN `preferredHourId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_hour` DROP COLUMN `visible`", undefined);
    }

}
