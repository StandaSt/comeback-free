import {MigrationInterface, QueryRunner} from "typeorm";

export class AddGlobalSettings1605195220664 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("INSERT INTO `global_settings` (name, value) VALUES ('deadlineNotification', '24')");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("DELETE FROM `global_settings` WHERE name='deadlineNotification'");
    }

}
