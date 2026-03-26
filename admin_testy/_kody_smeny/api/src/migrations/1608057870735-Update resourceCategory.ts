import {MigrationInterface, QueryRunner} from "typeorm";
import updateResourceCategory from "./scripts/updateResourceCategory";

export class UpdateResourceCategory1608057870735 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<void> {
        await updateResourceCategory(queryRunner,"ACTION_HISTORY",{label:"Administrace - Logování"});
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await updateResourceCategory(queryRunner,"ACTION_HISTORY",{label:"Logování"});
    }

}
