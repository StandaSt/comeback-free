import {MigrationInterface, QueryRunner} from "typeorm";

export class RemoveWaitingForApproval1603984182714 implements MigrationInterface {
    name = 'RemoveWaitingForApproval1603984182714'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `waitingForApproval`", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` ADD `waitingForApproval` tinyint NOT NULL DEFAULT '0'", undefined);
    }

}
