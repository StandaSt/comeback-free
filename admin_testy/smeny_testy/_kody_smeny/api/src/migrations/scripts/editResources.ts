import {QueryRunner} from "typeorm";
import editResource from "./editResource";


const editResources = async (queryRunner: QueryRunner, resources: { name: string, label?: string, description?: string, categoryName?: string, minimalCount?: number, requiredResource?: string[] }[]) => {
    for (const resource of resources) {
        await editResource(queryRunner, resource.name, resource.label, resource.description, resource.categoryName, resource.minimalCount, resource.requiredResource);
    }
};

export default editResources;