
export interface Variable {
  label: string;
  value: string;
  category?: string;
}


console.log("================================================");
console.log(window.data.variables);
export const variables=window.data.variables;
console.log("================================================");

