import {QueryRunner} from "typeorm";
import addResource from "./addResource";

const addResources = async (queryRunner: QueryRunner, resources: { name: string, label: string, description: string, categoryName: string, minimalCount?: number, requiredResource?: string[] }[]) => {
    for (const resource of resources) {
        const requiredResource = resource.requiredResource ? resource.requiredResource : [];
        const minimalCount = resource.minimalCount ? resource.minimalCount : 0;
        await addResource(queryRunner, resource.name, resource.label, resource.description, resource.categoryName, minimalCount, requiredResource);
    }
};

export default addResources;