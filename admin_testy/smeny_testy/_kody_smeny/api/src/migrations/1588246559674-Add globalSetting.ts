import {MigrationInterface, QueryRunner} from "typeorm";

export class AddGlobalSetting1588246559674 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("INSERT INTO `global_settings` (name, value) VALUES ('preferredDeadline', ?)", [new Date("2020-04-29T10:00:00.000Z")]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("DELETE FROM `global_settings` WHERE name='preferredDeadline'");
    }

}
