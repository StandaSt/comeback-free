import {MigrationInterface, QueryRunner} from "typeorm";

export class AddGlobalSettings1604667597630 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("INSERT INTO `global_settings` (name, value) VALUES ('evaluationCooldown', '24')");
        await queryRunner.query("INSERT INTO `global_settings` (name, value) VALUES ('evaluationTTL', '14')");
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("DELETE FROM `global_settings` WHERE name='evaluationCooldown'");
        await queryRunner.query("DELETE FROM `global_settings` WHERE name='evaluationTTL'");
    }
}
