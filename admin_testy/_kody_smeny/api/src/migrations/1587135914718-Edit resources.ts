import {MigrationInterface, QueryRunner} from "typeorm";
import removeResource from "./scripts/removeResource";
import addResource from "./scripts/addResource";
import addResources from "./scripts/addResources";

export class EditResources1587135914718 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await removeResource(queryRunner, "SHIFT_CAN_PLAN");
        await addResource(queryRunner,
            "SHIFT_CAN_PLAN",
            "Plánování směn",
            "Plánování směn.",
            "SHIFT",
            0,
            ["USER_SEE_ALL", "SHIFT_ROLE_TYPE_SEE_ALL", "BRANCH_SEE_SHIFT_WEEKS"])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResource(queryRunner, "SHIFT_CAN_PLAN")
        await addResources(queryRunner, [{
            name: "SHIFT_CAN_PLAN",
            label: "Plánování směn",
            description: "Plánování směn.",
            categoryName: "SHIFT",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL"],
        }])
    }

}
