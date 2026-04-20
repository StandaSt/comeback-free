import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1589097952420 implements MigrationInterface {
    name = 'AddResources1589097952420'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` ADD `waitingForApproval` tinyint NOT NULL DEFAULT 0", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `waitingForApproval` tinyint NOT NULL DEFAULT 0", undefined);
        await addResources(queryRunner, [{
            name: "SHIFT_CAN_PUBLISH",
            categoryName: "SHIFT",
            label: "Pubikování směn",
            description: "Může směnu publikovat, bez tohoto zdroje může směnu pouze dát ke schválení.",
            requiredResource: ["SHIFT_CAN_PLAN"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["SHIFT_CAN_PUBLISH"]);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `waitingForApproval`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `waitingForApproval`", undefined);
    }

}
