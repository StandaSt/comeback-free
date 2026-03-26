import {MigrationInterface, QueryRunner} from "typeorm";

export class EditRelationBetweenUserAndShiftHour1587659159864 implements MigrationInterface {
    name = 'EditRelationBetweenUserAndShiftHour1587659159864'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_hour` DROP FOREIGN KEY `FK_e82135fa73801b18398c1af2a23`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` CHANGE `employeeId` `dbWorkerId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD CONSTRAINT `FK_dc5dafed11bff65346a1aa2926e` FOREIGN KEY (`dbWorkerId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_hour` DROP FOREIGN KEY `FK_dc5dafed11bff65346a1aa2926e`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` CHANGE `dbWorkerId` `employeeId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD CONSTRAINT `FK_e82135fa73801b18398c1af2a23` FOREIGN KEY (`employeeId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

}
