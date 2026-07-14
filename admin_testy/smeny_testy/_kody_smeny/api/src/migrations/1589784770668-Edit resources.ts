import {MigrationInterface, QueryRunner} from "typeorm";
import editResources from "./scripts/editResources";

export class EditResources1589784770668 implements MigrationInterface {
    name = 'EditResources1589784770668'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("DROP INDEX `IDX_acdfb75d47e1733e1f3baba16f` ON `shift_week_template`", undefined);
        await editResources(queryRunner, [{
            name: "SHIFT_CAN_PLAN",
            requiredResource: ["USER_SEE_ALL", "BRANCH_SEE_SHIFT_WEEKS", "SHIFT_ROLE_TYPE_SEE_ALL", "BRANCH_SEE_ALL"],
        }])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE UNIQUE INDEX `IDX_acdfb75d47e1733e1f3baba16f` ON `shift_week_template` (`shiftWeekId`)", undefined);
        await editResources(queryRunner, [{
            name: "SHIFT_CAN_PLAN",
            requiredResource: ["USER_SEE_ALL", "BRANCH_SEE_SHIFT_WEEKS", "SHIFT_ROLE_TYPE_SEE_ALL"],
        }])
    }

}
