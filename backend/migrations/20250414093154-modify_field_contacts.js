module.exports = {
  async up(db) {
    await db.collection('contacts').updateMany(
      { senderChooseProgram: { $exists: false } },
      { $set: { senderChooseProgram: [] } }
    );

    await db.collection('contacts').updateMany(
      { senderChooseSchool: { $exists: false } },
      { $set: { senderChooseSchool: [] } }
    );

    console.log("✅ Made 'senderChooseProgram' and 'senderChooseSchool' optional with default []");

    await db.collection('contacts').updateMany(
      { status: { $type: 'int' } },
      [{ $set: { status: { $toString: "$status" } } }]
    );
    console.log("✅ Converted numeric status to string.");

     // Add the `processDate` field to all existing documents
     await db.collection('contacts').updateMany(
      { processDate: { $exists: false } }, // Only update documents that do not have the `processDate` field
      {
        $set: { 
          processDate: new Date().toISOString() // Assign current date-time as the default value for processDate
        }
      }
    );
    console.log("✅ processDate field added to posts.");
  },

  async down(db) {
    // Optionally, remove these fields to simulate rollback
    await db.collection('contacts').updateMany(
      {},
      { $unset: { senderChooseProgram: "", senderChooseSchool: "" } }
    );

    console.log("🔁 Reverted optional fields by unsetting them");
    
    await db.collection('contacts').updateMany(
      { status: { $type: 'string' } },
      [{ $set: { status: { $toInt: "$status" } } }]
    );
    console.log("🔁 Reverted status from string back to number.");

    // Remove the `processDate` field during rollback
    await db.collection('contacts').updateMany(
      {},
      {
        $unset: { processDate: "" }
      }
    );
    console.log("🔁 processDate field removed from posts.");
  }
};
